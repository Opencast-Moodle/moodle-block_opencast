<?php

namespace block_opencast\local;

use block_opencast\opencast_state_exception;

class ingest_uploader
{
    const STATUS_INGEST_CREATING_MEDIA_PACKAGE = 121;

    const STATUS_INGEST_ADDING_EPISODE_CATALOG = 122;

    const STATUS_INGEST_ADDING_FIRST_TRACK = 123;

    const STATUS_INGEST_ADDING_SECOND_TRACK = 124;

    const STATUS_INGEST_ADDING_ACL_ATTACHMENT = 121;

    const STATUS_INGEST_INGESTING = 121;

    # todo what about create group?!

    protected function process_upload_job($job) {
        global $DB;
        $stepsuccessful = false;
        $apibridge = apibridge::get_instance($job->ocinstanceid);

        switch ($job->status) {
            case upload_helper::STATUS_READY_TO_UPLOAD:
                $this->update_status_with_mediapackage($job, self::STATUS_INGEST_CREATING_MEDIA_PACKAGE, true, true);
            case self::STATUS_INGEST_CREATING_MEDIA_PACKAGE:

                try {
                    $mediapackage = $apibridge->ingest_create_media_package();
                    // TODO check mediapackage ?
                    $stepsuccessful = true;
                    mtrace('... media package created');
                    // Move on to next status.
                    $this->update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_EPISODE_CATALOG, true, false, false, $mediapackage);
                } catch (opencast_state_exception $e) {
                    mtrace('... failed to create media package');
                }
                break;

            case self::STATUS_INGEST_ADDING_EPISODE_CATALOG:
                try {


                    // Check if series exists.
                    $metadata = json_decode($job->metadata);
                    $mtseries = array_search('isPartOf', array_column($metadata, 'id'));
                    $series = null;

                    if ($mtseries !== false) {
                        $series = $apibridge->get_series_by_identifier($metadata[$mtseries]->value);
                    }

                    if (!$series) {
                        $series = $apibridge->ensure_course_series_exists($job->courseid, $job->userid);
                    }

                    if ($series) {
                        $stepsuccessful = true;
                        mtrace('... series exists');
                        // Move on to next status.
                        $this->update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_FIRST_TRACK, true, false, false, $mediapackage);
                    }
                } catch (opencast_state_exception $e) {
                    mtrace('... series creation still in progress');
                }
                break;

            case self::STATUS_CREATING_EVENT:

                $eventids = array();

                if ($job->opencasteventid) {
                    array_push($eventids, $job->opencasteventid);
                } else if (get_config('block_opencast', 'reuseexistingupload_' . $job->ocinstanceid)) {
                    // Check, whether this file was uploaded any time before.
                    $params = array(
                        'contenthash_presenter' => $job->contenthash_presenter,
                        'contenthash_presentation' => $job->contenthash_presentation,
                        'ocinstanceid' => $job->ocinstanceid
                    );

                    // Search for files already uploaded to opencast.
                    $sql = "SELECT opencasteventid " .
                        "FROM {block_opencast_uploadjob} " .
                        "WHERE ocinstanceid = :ocinstanceid AND (contenthash_presenter = :contenthash_presenter " .
                        "OR contenthash_presentation = :contenthash_presentation) " .
                        "GROUP BY opencasteventid ";

                    $eventids = $DB->get_records_sql($sql, $params);
                    if ($eventids) {
                        $eventids = array_keys($eventids);
                        mtrace('... applying reuse of existing upload');
                    }
                }

                $metadata = json_decode($job->metadata);
                $mtseries = array_search('isPartOf', array_column($metadata, 'id'));
                $series = null;
                if ($mtseries !== false) {
                    $series = $apibridge->get_series_by_identifier($metadata[$mtseries]->value);
                }

                if (!$series) {
                    $series = $apibridge->get_default_course_series($job->courseid);

                    // Set series metadata.
                    if ($mtseries !== false) {
                        $metadata[$mtseries]->value = $series->identifier;
                    } else {
                        $metadata[] = array('id' => 'isPartOf', 'value' => $series->identifier);
                        $job->metadata = json_encode($metadata);
                    }
                }
                $event = $apibridge->ensure_event_exists($job, $eventids);

                // Check result.
                if (!isset($event->identifier)) {
                    throw new \moodle_exception('missingevent', 'block_opencast');
                } else {
                    mtrace('... video uploaded');
                    $this->update_status($job, self::STATUS_UPLOADED);
                    $stepsuccessful = true;

                    // Update eventid.
                    $job->opencasteventid = $event->identifier;
                    $DB->update_record('block_opencast_uploadjob', $job);
                }
                break;

            case self::STATUS_UPLOADED:

                // Continue with post-upload tasks.

                // Verify the upload.
                if (!$job->opencasteventid) {
                    throw new \moodle_exception('missingeventidentifier', 'block_opencast');
                }

                if (!($event = $apibridge->get_already_existing_event(array($job->opencasteventid)))) {
                    mtrace('... event does not exist');
                    break;
                }

                // Mark the job as uploaded.
                $job->opencasteventid = $event->identifier;

                // For a new event do acl and series are already set, event will be processed
                // in opencast, so it is not possible to modify event at this state.
                if (isset($event->newlycreated) && $event->newlycreated) {
                    return $event;
                }

                if (boolval(get_config('block_opencast', 'group_creation_' . $job->ocinstanceid))) {
                    // Ensure the assignment of a suitable role.
                    if (!$apibridge->ensure_acl_group_assigned($event->identifier, $job->courseid, $job->userid)) {
                        mtrace('... group not yet assigned.');
                        break;
                    }
                    mtrace('... group assigned');
                }

                // Ensure the assignment of a course series.
                $assignedseries = $event->is_part_of;
                $courseseries = $DB->get_records('tool_opencast_series',
                    array('courseid' => $job->courseid, 'ocinstanceid' => $job->ocinstanceid));

                if (!array_search($assignedseries, array_column($courseseries, 'series'))) {
                    // Try to assign series again.
                    $mtseries = array_search('isPartOf', array_column(json_decode($job->metadata), 'id'));

                    if (!array_search($mtseries, array_column($courseseries, 'series'))) {
                        $mtseries = $apibridge->get_default_course_series($job->courseid)->identifier;
                    }

                    if (!$apibridge->assign_series($event->identifier, $mtseries)) {
                        mtrace('... series not yet assigned.');
                        break;
                    }
                }

                mtrace('... series assigned');

                // If a event was created, the upload is finished and the event can be returned.
                return $event;
        }

        // If the current step (creation of group or series) was successful, the function process_upload_job can be
        // called recursively to continue with the next bit of work.
        if ($stepsuccessful) {
            return $this->process_upload_job($job);
        } else {
            // Otherwise, false is returned, which causes to cronjob to try again later.
            return false;
        }

    }

    public static function update_status_with_mediapackage(&$job, $status, $setmodified = true, $setstarted = false, $setsucceeded = false, $mediapackage = null) {
        global $DB;
        $time = time();
        if ($setstarted) {
            $job->timestarted = $time;
        }
        if ($setmodified) {
            $job->timemodified = $time;
        }
        if ($setsucceeded) {
            $job->timesucceeded = $time;
        }
        if ($mediapackage) {
            $job->mediapackage = $mediapackage;
        }

        $job->status = $status;

        $DB->update_record('block_opencast_uploadjob', $job);
    }
}