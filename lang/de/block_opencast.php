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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'block_opencast', language 'de'
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['aclgroupdeleted'] = 'Der Zugriff auf das Video {$a->title} wurde gelöscht';
$string['aclrolesadded'] = 'Der Zugriff für die nicht permanenten Rollen auf das Video {$a->title} wurde gelöscht.';
$string['aclrolesdeleted'] = 'Der Zugriff für die nicht permanenten Rollen auf das Video {$a->title} wurde hinzugefügt.';
$string['accesspolicies'] = 'Zugangsrichtlinien';
$string['aclrolesname'] = 'Rollen';
$string['aclrolesnamedesc'] = 'Sie können den Platzhalter [COURSEID] in dem Rollennamen nutzen, der automatisch durch die Kurs-ID ersetzt wird..';
$string['actions'] = 'Kommagetrennte Liste von Aktionen';
$string['addvideo'] = 'Video hinzufügen';
$string['addrole'] = 'Neue Rolle hinzufügen';
$string['apipassword'] = 'Passwort for API user';
$string['apipassworddesc'] = 'Geben Sie das Opencast-Passwort für den Super-User (Stellvertreter Nutzer) ein mit dem die Anfragen an Opencast ausgeführt werden sollen.';
$string['apipasswordempty'] = 'Das Passwort für den Super-User ist nicht korrekt konfiguriert. Korrgieren sie das Passwort in den Einstellungen des Plugins';
$string['apisettings'] = 'Externe API Verbindung';
$string['apiurl'] = 'Opencast API url';
$string['apiurldesc'] = 'Geben Sie die Url zum the Opencast system ein, zum Beispiel: opencast.example.com';
$string['apipasswordempty'] = 'Die Url zum Opencast System (API-Url) ist nicht korrekt konfiguriert. Korrgieren sie dies in den Einstellungen des Plugins';
$string['apiusername'] = 'Username des Stellvertreter-Nutzers für API Anfragen';
$string['apiusernamedesc'] = 'Für alle Anfragen an die Opencast External API verwendet Moodle diesen Nutzer.
    Die Berechtigungen werden über passende Rollenzuweisungen gesteuert.';
$string['apiusernameempty'] = 'Der Username für den Stellvertreter-Nutzer ist nicht korrekt konfiguriert. Korrgieren sie dies in den Einstellungen des Plugins';
$string['connecttimeout'] = 'Connection timeout';
$string['connecttimeoutdesc'] = 'Geben sie ein, wie lange Moodle versuchen soll, sich mit Opencast zu verbinden';
$string['countfailed'] = 'Fehlgeschlagen';
$string['createdby'] = 'Erstellt von';
$string['cronsettings'] = 'Einstellungen für die Uploads';
$string['deleteaclgroup'] = 'Video von der Liste löschen';
$string['delete_confirm'] = 'Möchten Sie diese Rolle wirklich löschen?';
$string['deletegroupacldesc'] = 'Sie wollen den Zugriff auf das Video löschen. Wenn sie das tun,
    wird das Video ab sofort weder im Dateiauswahl-Dialog noch in der Liste der verfügbaren Videos angezeigt. Bereits eingebettete Videos bleiben erhalten.
    Das Video wird nicht in Opencast gelöscht.';
$string['dodeleteaclgroup'] = 'Zugriff auf das Video löschen';
$string['edituploadjobs'] = 'Bearbeite die Aufträge zum Hochladen von Videos';
$string['eventuploadsucceeded'] = 'Upload erfolgreich';
$string['eventuploadfailed'] = 'Upload fehlgeschlagen';
$string['errorgetblockvideos'] = 'Die Liste der Videos kann nicht geladen werden (Error: {$a})';
$string['form_seriesid'] = 'Serien ID';
$string['form_seriestitle'] = 'Serientitel';
$string['gotooverview'] = 'Zur Übersicht...';
$string['groupcreation'] = 'Gruppe erstellen';
$string['groupcreationdesc'] = 'Wenn die Checkbox markiert ist, wird während des Uploads eine Gruppe erstellt.';
$string['groupname'] = 'Gruppenname';
$string['groupnamedesc'] = 'Gruppe, zu welcher das Video hinzugefügt wird. Wichtig: Die Länge des Gruppennamens ist auf 128 Bytes beschränkt!';
$string['heading_role'] = 'Rolle';
$string['heading_actions'] = 'Aktions';
$string['heading_delete'] = 'Löschen';
$string['heading_permanent'] = 'Permanent';
$string['hstart_date'] = 'Datum';
$string['htitle'] = 'Name';
$string['hworkflow_state'] = 'Status';
$string['hpublished'] = 'Veröffentlicht';
$string['invalidacldata'] = 'Ungültige Berechtigungen';
$string['limituploadjobs'] = 'Anzahl der Uploads pro cron job';
$string['limituploadjobsdesc'] = 'Legen sie die Anzahl der Uploads pro cron job fest. Die Ausführung des cronjobs kann hier konfiguriert werden: {$a}';
$string['limitvideos'] = 'Anzahl der Videos';
$string['limitvideosdesc'] = 'Anzahl der Videos, die im Block angezeigt werden';
$string['missingevent'] = 'Anlegen eines Events ist fehlgeschlagen';
$string['missinggroup'] = 'Gruppenrolle in Opendcast konnte nicht erzeugt werden';
$string['missingseries'] = 'Die Serie für das Video konnte nicht erzeugt werden';
$string['missingseriesassignment'] = 'Das Video konnte der Serie nicht zugewiesen werden';
$string['morevideos'] = 'Mehr Videos...';
$string['mstatereadytoupload'] = 'Bereit zum Hochladen';
$string['mstatesuploading'] = 'Upload läuft...';
$string['mstatetransferred'] = 'Hochgeladen';
$string['mstateunknown'] = 'unbekannter Zustand';
$string['needphp55orhigher'] = 'PHP Version 5.5 oder höher ist erforderlich';
$string['notpublished'] = 'Nicht veröffentlicht';
$string['novideosavailable'] = 'Kein Videos vorhanden';
$string['opencast:addinstance'] = 'Neuen Opencast Block hinzufügen';
$string['opencast:addvideo'] = 'Ein neues Video zu Opencast hinzufügen';
$string['opencast:myaddinstance'] = 'Den Opencast Block zum Dashboard hinzufügen';
$string['opencast:viewunpublishedvideos'] = 'Alle (auch nicht veröffentlichte) Videos sehen';
$string['overview'] = 'Übersicht';
$string['overviewsettings'] = 'Einstellungen für die Übersichtsseite';
$string['pluginname'] = 'Opencast Videos';
$string['processupload'] = 'Upload starten';
$string['publishtoengage'] = 'Nach Engage veröffentlicht';
$string['publishtoengagedesc'] = 'Wählen sie diese Option, wenn das Video nach dem Upload über den Engage-Player veröffentlicht werden soll. Der festgelegt Workflow muss dies unterstützen.';
$string['rolename'] = 'Rollenname';
$string['seriescreated'] = 'Die Serie wurde erstellt.';
$string['seriesnotcreated'] = 'Die Serie konnte nicht erstellt werden.';
$string['seriesidsaved'] = 'Die Serien ID wurde gespeichert.';
$string['seriesidnotvalid'] = 'Die Serie existiert nicht.';
$string['series_exists'] = 'Die Serie mit der ID \'{$a}\' konnte nicht in Opencast gefunden werden. Bitte kontaktieren Sie Ihren Systemadministrator.';
$string['seriesname'] = 'Serienname';
$string['seriesnamedesc'] = 'Serie, zu welcher das Video hinzugefügt wird.';
$string['settings'] = 'Opencast Videos';
$string['submit'] = 'Speichern';
$string['ocstatefailed'] = 'Fehlgeschlagen';
$string['ocstateprocessing'] = 'in Verarbeitung';
$string['ocstatesucceeded'] = 'Abgeschlossen';
$string['uploadfilelimit'] = 'Maximale Videogröße';
$string['uploadfilelimitdesc'] = 'Limitiert die maximale Dateigröße von hochzuladenden Videos.';
$string['uploadingeventfailed'] = 'Event konnte nicht erstellt werden';
$string['uploadjobssaved'] = 'Upload Aufträge wurden erzeugt';
$string['uploadqueuetoopencast'] = 'Videos bereit zum Hochladen';
$string['uploadworkflow'] = 'Workflow, der nach dem Hochladen ausgeführt wird';
$string['uploadworkflowdesc'] = 'Tragen sie hier die Kurzbezeichnung eines Workflows ein, der nach dem Hochladen eines Videos zu Opencast ausgeführt werden soll.
    Falls keine Kurzbezeichnung eingegeben wird, verwendet Moodle den Standard-Workflow (ng-schedule-and-upload). Erkundigen sie sich beim Administrator von Opencast nach verfügbaren
    Workflows und deren Kurzbezeichnung.';
$string['videosavailable'] = 'Opencast Videos, die für diesen Kurs freigegeben sind';
$string['videonotfound'] = 'Video nicht gefunden';
$string['videostoupload'] = 'Videos, die nach Opencast hochgeladen werden sollen';
$string['wrongmimetypedetected'] = 'Beim Hochladen von {$a->filename} aus dem Kurs {$a->coursename} wurde ein ungültiger Mimetype verwendet.
    Es können nur Videodateien hochgeladen werden!';
