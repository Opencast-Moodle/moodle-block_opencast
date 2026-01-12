<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  See <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'block_opencast', language 'da'
 *
 * @package    block_opencast
 * @copyright 2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aclnothingtobesaved'] = 'Ingen ændringer af synligheden blev foretaget.';
$string['aclownerrole'] = 'ACL ejer-rolle';
$string['aclrolesadded'] = 'Ændring af synlighed er igangsat for at give alle studerende på kurset adgang til videoen: {$a->title}<br />Opdater venligst siden om lidt for at se den aktuelle status.';
$string['aclrolesaddedgroup'] = 'Ændring af synlighed er igangsat for at give studerende i de valgte grupper adgang til videoen: {$a->title}<br />Opdater venligst siden om lidt for at se den aktuelle status.';
$string['aclrolesdeleted'] = 'Ændring af synlighed er igangsat for at fjerne adgangen for alle studerende på kurset til videoen: {$a->title}<br />Opdater venligst siden om lidt for at se den aktuelle status.';
$string['actions'] = 'Komma-separeret liste over handlinger';
$string['addlti_defaulttitle'] = 'Opencast videoer';
$string['addvideo'] = 'Tilføj video';
$string['adhocfiledeletion'] = 'Slet videofil fra Moodle';
$string['adminchoice_noworkflow'] = "-- Intet workflow --";
$string['allowdownloadtranscriptionsetting'] = 'Tillad download af transskriptioner';
$string['alloweduploadwfconfigs'] = 'Tilladte konfigurationer for upload-workflows';
$string['allowunassign'] = 'Tillad at fjerne tilknytning fra kursus';
$string['backupopencastvideos'] = 'Inkluder videoer fra Opencast-instansen {$a} i dette kursus';
$string['batchupload'] = 'Tilføj videoer (batch)';
$string['blocksettings'] = 'Indstillinger for en blok-instans';
$string['cachevalidtime'] = 'Cache gyldighedstid';
$string['changeowner'] = 'Skift ejer';
$string['changevisibility'] = 'Ret synlighed';
$string['contributor'] = 'Bidragyder(e)';
$string['created'] = 'Oprettet den';
$string['createdby'] = 'Uploadet af';
$string['createseriesforcourse'] = 'Opret ny serie';
$string['creator'] = 'Oplægsholder(e)';
$string['date'] = 'Startdato';
$string['default'] = 'Standard';
$string['delete_block_and_mapping'] = 'Slet blok og serie-mapping';
$string['delete_confirm_metadata'] = 'Er du sikker på, at du vil slette dette metadata-felt?';
$string['delete_confirm_role'] = 'Er du sikker på, at du vil slette denne rolle?';
$string['delete_mapping_explanation'] = 'Opencast-blokken holder styr på, hvilken Opencast-serie der er knyttet til kurset.<br>Du kan vælge, om mappingen skal slettes.<br>Hvis du sletter den, vil serien ikke længere fremgå, når du opretter blokken igen.';
$string['delete_metadata'] = 'Slet metadata-felt';
$string['delete_role'] = 'Slet rolle';
$string['deletecheck_title_modal'] = 'Fjern Opencast-blok?';
$string['deletedraft'] = 'Slet video før overførsel til Opencast';
$string['deleteevent'] = 'Slet en begivenhed (event) i Opencast';
$string['deleteworkflow'] = 'Workflow der skal startes før en begivenhed slettes';
$string['deleting'] = 'Bliver slettet';
$string['description'] = 'Beskrivelse';
$string['downloadvideo'] = 'Download video';
$string['duplicateworkflow'] = 'Workflow til duplikering af begivenheder';
$string['duration'] = 'Varighed';
$string['editorbaseurl'] = 'Opencast Editor Base URL';
$string['editseries'] = 'Rediger serie';
$string['enablechunkupload'] = 'Aktiver chunk-upload';
$string['enableopencasteditorlink'] = 'Vis link til Opencast Editor i handlingsmenuen';
$string['enableopencaststudiolink'] = 'Vis link til Opencast Studio';
$string['enableopencaststudioreturnbtn'] = 'Vis en "tilbage"-knap i Studio';
$string['enableschedulingchangevisibility'] = 'Planlæg ændring af synlighed';
$string['enableuploadwfconfigpanel'] = 'Vis workflow-konfigurationer under upload';
$string['engageurl'] = 'URL til Opencast Engage server';
$string['error_seriesid_missing_course'] = 'Kurset {$a->coursefullname} (ID: {$a->courseid}) har ingen kursus-serie. Begivenheden ({$a->eventid}) kunne ikke gendannes.';
$string['error_seriesid_missing_opencast'] = 'Serien til kurset {$a->coursefullname} (ID: {$a->courseid}) blev ikke fundet i Opencast-systemet. Begivenheden ({$a->eventid}) kunne ikke gendannes.';
$string['error_seriesid_not_matching'] = 'Kurset {$a->coursefullname} (ID: {$a->courseid}) har en kursus-serie, der ikke matcher opgavens serie-ID. Begivenheden ({$a->eventid}) kunne ikke gendannes.';
$string['error_workflow_not_exists'] = 'Workflowet {$a->duplicateworkflow} blev ikke fundet i Opencast-systemet. Begivenheden ({$a->eventid}) kunne ikke gendannes for kurset {$a->coursefullname} (ID: {$a->courseid}).';
$string['error_workflow_not_started'] = 'Workflowet til kopiering af videoen ({$a->eventid}) tilknyttet kurset {$a->coursefullname} (ID: {$a->courseid}) kunne ikke startes.';
$string['error_workflow_setup_missing'] = 'Pluginet tool_opencast er ikke konfigureret korrekt. Workflowet til duplikering mangler!';
$string['erroremailsubj'] = 'Fejl under udførelse af Opencast duplikeringsopgave';
$string['errorgetblockvideos'] = 'Listen kunne ikke hentes (Fejl: {$a})';
$string['errorrestoremissingevents_subj'] = 'Opencast fejl under gendannelsesproces';
$string['errorrestoremissingseries_subj'] = 'Opencast fejl under gendannelsesproces';
$string['faileduploadretrylimit'] = 'Grænse for fejlslagne upload-forsøg';
$string['filetypes'] = 'Accepterede filtyper';
$string['general_settings'] = 'Generelle indstillinger';
$string['gotooverview'] = 'Gå til oversigt...';
$string['groupcreation'] = 'Opret en gruppe';
$string['groupname'] = 'Gruppenavn';
$string['heading_actions'] = 'Handlinger';
$string['heading_batchable'] = 'Kan køre i batch';
$string['heading_datatype'] = 'Felttype';
$string['heading_defaultable'] = 'Kan have standardværdi';
$string['heading_description'] = 'Feltbeskrivelse';
$string['heading_name'] = 'Feltnavn';
$string['heading_params'] = 'Parametre (JSON)';
$string['heading_permanent'] = 'Permanent';
$string['heading_readonly'] = 'Skrivebeskyttet';
$string['heading_required'] = 'Påkrævet';
$string['heading_role'] = 'Rolle';
$string['identifier'] = 'Identifikator';
$string['importmode'] = 'Import-tilstand';
$string['importseries'] = 'Importer serie';
$string['importvideos_wizard_event_cb_title'] = '{$a->title} (ID: {$a->identifier})';
$string['importvideos_wizard_series_cb_title'] = 'Serie: {$a->title} (ID: {$a->identifier})';
$string['importvideos_wizard_unselectableeventreason'] = 'videovalg';
$string['ingest_endpoint_notfound'] = 'Ingest-endpoint er ikke tilgængeligt; dette skal løses af en systemadministrator.';
$string['ingestupload'] = 'Ingest upload';
$string['initialvisibilitystatus'] = 'Videoens oprindelige synlighed';
$string['invalidacldata'] = 'Ugyldige ACL-data';
$string['invalidmetadatafield'] = 'Ugyldigt metadata-felt fundet: {$a}';
$string['language'] = 'Sprog';
$string['license'] = 'Licens';
$string['limituploadjobs'] = 'Begræns antal upload-jobs via cron';
$string['limitvideos'] = 'Antal videoer';
$string['limitvideosdesc'] = 'Maksimalt antal videoer der skal vises i blokken';
$string['loading'] = 'Indlæser...';
$string['location'] = 'Lokation';
$string['managedefaultsforuser'] = 'Administrer standardværdier';
$string['manageseriesforcourse'] = 'Administrer serie';
$string['maxseries'] = 'Maksimalt antal serier';
$string['maxtranscriptionupload'] = 'Maksimalt antal sæt til upload';
$string['mediatype'] = 'Mediekilde';
$string['metadata'] = 'Begivenheds-metadata (Event)';
$string['metadataseries'] = 'Serie-metadata';
$string['missingevent'] = 'Oprettelse af begivenhed fejlede';
$string['missinggroup'] = 'Manglende gruppe i Opencast';
$string['missingseries'] = 'Manglende serie i Opencast';
$string['morethanonedefaultserieserror'] = 'Dette kursus har mere end én standardserie. Kontakt venligst din systemadministrator.';
$string['morevideos'] = 'Flere videoer...';
$string['notificationeventstatus'] = 'Tillad notifikation om processtatus for begivenheder';
$string['notificationeventstatusdeletion'] = 'Ryd op i notifikationsjobs efter (dage)';
$string['notificationeventstatusteachers'] = 'Underret alle lærere på kurset om processtatus for begivenheden';
$string['novideosavailable'] = 'Ingen videoer tilgængelige';
$string['ocstatecapturing'] = 'Optager (Capturing)';
$string['ocstatefailed'] = 'Fejlet';
$string['ocstateneedscutting'] = 'Skal klippes';
$string['ocstateprocessing'] = 'Behandler';
$string['ocstatesucceeded'] = 'Gennemført';
$string['offerchunkuploadalternative'] = 'Tilbyd filvælger som alternativ';
$string['only_delete_block'] = 'Slet blok, men behold serie-mapping';
$string['opencast:addinstance'] = 'Tilføj en ny Opencast upload-blok';
$string['opencast:addvideo'] = 'Tilføj en ny video til Opencast upload-blokken';
$string['opencast:createseriesforcourse'] = 'Opret en ny serie i Opencast til et Moodle-kursus';
$string['opencast:deleteevent'] = 'Slet en video (event) i Opencast permanent';
$string['opencast:downloadvideo'] = 'Download færdigbehandlede videoer';
$string['opencast:importseriesintocourse'] = 'Importer en eksisterende Opencast-serie til et Moodle-kursus';
$string['opencast:manageseriesforcourse'] = 'Administrer Opencast-serien for et Moodle-kursus: Tilgå manageseries.php, fjern tilknytning og vælg standardserie.';
$string['opencast:myaddinstance'] = 'Tilføj en ny Opencast upload-blok til betjeningspanelet';
$string['opencast:sharedirectaccessvideolink'] = 'Del direkte link til video';
$string['opencast:startworkflow'] = 'Start manuelt workflows for videoer';
$string['opencast:unassignevent'] = 'Fjern tilknytning af en video fra kurset, hvor den blev uploadet.';
$string['opencast:viewunpublishedvideos'] = 'Se alle videoer fra Opencast-serveren, også når de ikke er udgivet';
$string['opencaststudiobaseurl'] = 'Opencast Studio Base URL';
$string['opencaststudionewtab'] = 'Viderestil til Studio i en ny fane';
$string['opencaststudioreturnbtnlabel'] = 'Tekst på Studios "tilbage"-knap';
$string['overview'] = 'Oversigt';
$string['owner'] = 'Ejer';
$string['planned'] = 'Planlagt';
$string['pluginname'] = 'Opencast videoer';
$string['presentation'] = 'Præsentationsvideo';
$string['presenter'] = 'Oplægsholdervideo';
$string['privacy:metadata'] = 'Opencast-blokken gemmer ingen personlige data.';
$string['publisher'] = 'Udgiver';
$string['publishtoengage'] = 'Udgiv til Engage';
$string['readonly_disabled_tooltip_text'] = 'Kan ikke indstilles til skrivebeskyttet, når den er sat til at være påkrævet.';
$string['recordvideo'] = 'Optag video';
$string['restoreopencastvideos'] = 'Gendan videoer fra Opencast-instansen {$a}';
$string['reuseexistingupload'] = 'Genbrug eksisterende uploads';
$string['rightsHolder'] = 'Rettighedshaver';
$string['rolename'] = 'Rollenavn';
$string['scheduledvisibilitystatus'] = 'Skift videoens synlighed til';
$string['scheduledvisibilitytime'] = 'Skift videoens synlighed den';
$string['series'] = 'Serie';
$string['series_already_exists'] = 'Dette kursus er allerede tilknyttet en serie.';
$string['seriescreated'] = 'Serien blev oprettet.';
$string['seriesname'] = 'Serienavn';
$string['seriesnotcreated'] = 'Serien kunne ikke oprettes.';
$string['seriesoverview'] = 'Serieoversigt';
$string['seriesoverviewof'] = 'Serieoversigt for {$a} instans';
$string['settings'] = 'Opencast videoer';
$string['settings_page'] = 'Indstillinger';
$string['settings_page_url'] = '{$a} indstillinger';
$string['source'] = 'Kilde';
$string['startDate'] = 'Dato';
$string['startTime'] = 'Tidspunkt';
$string['startworkflow'] = 'Start workflow';
$string['subjects'] = 'Emner';
$string['submit'] = 'Gem ændringer';
$string['termsofuse'] = 'Brugsvilkår';
$string['title'] = 'Titel';
$string['tool_requirement_not_fulfilled'] = 'Den påkrævede version af tool_opencast er ikke installeret.';
$string['transcription_flavor_confirm_delete'] = 'Er du sikker på, at du vil slette dette flavor-par?';
$string['transcription_flavor_delete'] = 'Slet flavor-par';
$string['transcription_flavor_key'] = 'Flavor-nøgle';
$string['transcription_flavor_value'] = 'Flavor-værdi';
$string['transcriptionfileextensions'] = 'Tilladte filendelser for transskription';
$string['transcriptionflavors'] = 'Transskriptionstjeneste-typer (Flavors)';
$string['transcriptionworkflow'] = 'Workflow til transskription (tale til tekst)';
$string['type'] = 'Medietype';
$string['updatemetadata'] = 'Opdater metadata for denne begivenhed';
$string['upload'] = 'Fil-upload';
$string['uploadfileextensions'] = 'Tilladte filendelser';
$string['uploadfilelimit'] = 'Grænse for videostørrelse';
$string['uploadfilesizelimitmode'] = 'Tilstand for begrænsning af videostørrelse';
$string['uploadingeventfailed'] = 'Oprettelse af begivenhed fejlede';
$string['uploadtimeout'] = 'Timeout for upload fra Moodle til Opencast';
$string['uploadworkflow'] = 'Workflow der skal startes efter upload';
$string['video'] = 'Video';
$string['video_already_uploaded'] = 'Videoen er allerede uploadet';
$string['videosavailable'] = 'Videoer tilgængelige i dette kursus';
$string['visibility'] = 'Videoens synlighed';
$string['visibility_group'] = 'Tillad alle studerende i de valgte grupper at se videoen';
$string['visibility_massaction'] = 'Synlighed for de(n) valgte video(er)';
