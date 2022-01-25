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
 * Search for courses..
 *
 * This page display the community course search form.
 * It also handles downloading a course template.
 *
 * @package     block_customhub
 * @copyright   2022, ISB Bayern
 * @author      Peter Mayer, peter.mayer@isb.bayern.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require('../../config.php');
require_once($CFG->dirroot . '/blocks/customhub/locallib.php');
require_once($CFG->dirroot . '/blocks/customhub/classes/local/block_customhub_helper.php');

$customhubhelper = new \block_customhub\local\block_customhub_helper();
$registrationmanager = new tool_customhub\registration_manager();
$registeredhubs = $registrationmanager->get_registered_on_hubs();
$registeredhub = array_shift($registeredhubs);

// Only logged in users are allowed.
require_login();

$contextsystem = context_system::instance();
$PAGE->set_url('/blocks/customhub/communitysearch.php');
$PAGE->set_context($contextsystem);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title(get_string('searchcourse', 'block_customhub'));
$PAGE->navbar->add(get_string('searchcourse', 'block_customhub'));

$search = optional_param('search', null, PARAM_TEXT);


//if no capability to search course, display an error message
$usercansearch = has_capability('block/customhub:addcommunity', $contextsystem, $USER);
$usercandownload = has_capability('block/customhub:downloadcommunity', $contextsystem);
if (empty($usercansearch)) {
    $notificationerror = get_string('cannotsearchcommunity', 'block_customhub');
} else if (!extension_loaded('xmlrpc')) {
    $notificationerror = $OUTPUT->doc_link('admin/environment/php_extension/xmlrpc', '');
    $notificationerror .= get_string('xmlrpcdisabledcommunity', 'block_customhub');
}
if (!empty($notificationerror)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('searchcommunitycourse', 'block_customhub'), 3, 'main');
    echo $OUTPUT->notification($notificationerror);
    echo $OUTPUT->footer();
    die("error");
}

$customhubmanager = new block_customhub_manager();
$renderer = $PAGE->get_renderer('block_customhub');


/// Check if the page has been called with trust argument
$add = optional_param('add', -1, PARAM_INT);
$confirm = optional_param('confirmed', false, PARAM_INT);

// Enrol to a distant course.
if ($add != -1 and $confirm and confirm_sesskey()) {
    require_once($CFG->dirroot . "/blocks/customhub/classes/local/block_customhub_helper.php");
    $courseregid = required_param('crid', PARAM_INT);

    $helper = new \block_customhub\local\block_customhub_helper();
    $collaborationcourse = $helper->create_collaboration_course($SESSION->hubcourselist[$courseregid]);

    if (empty($collaborationcourse)) {
        redirect($CFG->wwwroot . '/my');
        die;
    }

    // After creation, redirect the user to the created course.
    redirect($CFG->wwwroot . '/course/view.php?id=' . $collaborationcourse->id);
    die();
}


/// Download
$download = optional_param('download', -1, PARAM_INT);
$downloadcourseid = optional_param('downloadcourseid', '', PARAM_INT);
if ($usercandownload and $download != -1 and !empty($downloadcourseid) and confirm_sesskey()) {
    // $huburl = optional_param('huburl', false, PARAM_URL);
    $coursefullname = optional_param('coursefullname', '', PARAM_ALPHANUMEXT);
    $backupsize = optional_param('backupsize', 0, PARAM_INT);
    $reqsource = optional_param('reqsource', 0, PARAM_INT);

    $course = new stdClass();
    $course->fullname = $coursefullname;
    $course->id = $downloadcourseid;
    $course->huburl = $registeredhub->huburl;

    //OUTPUT: display restore choice page
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('downloadingcourse', 'block_customhub'), 3, 'main');
    $sizeinfo = new stdClass();
    $sizeinfo->total = number_format($backupsize / 1000000, 2);
    echo html_writer::tag(
        'div',
        get_string('downloadingsize', 'block_customhub', $sizeinfo),
        ['class' => 'textinfo']
    );
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    $filenames = $customhubmanager->block_customhub_download_course_backup($course, $reqsource);
    echo html_writer::tag(
        'div',
        get_string('downloaded', 'block_customhub'),
        ['class' => 'textinfo']
    );

    $ccat = $customhubhelper->get_users_course_category();
    $customhubhelper->start_async_restore($filenames['storedfile'], $ccat->id);

    echo $OUTPUT->notification(get_string(
        'restorestarted',
        'block_customhub',
        '/downloaded_backup/' . $filenames['privatefile']
    ), 'notifysuccess');

    // echo $renderer->restore_confirmation_box($filenames['tmpfile'], $context);
    $url = new moodle_url('/blocks/mbsnewcourse/restore.php');
    redirect($url, 'Automatic redirect to course restore', 2);
    
    echo $OUTPUT->footer();
    die();
}




//Get form default/current values
$fromformdata['coverage'] = optional_param('coverage', 'all', PARAM_TEXT);
$fromformdata['licence'] = optional_param('licence', 'all', PARAM_ALPHANUMEXT);
$fromformdata['subject'] = optional_param('subject', 'all', PARAM_ALPHANUMEXT);
$fromformdata['audience'] = optional_param('audience', 'all', PARAM_ALPHANUMEXT);
$fromformdata['language'] = optional_param('language', 'all', PARAM_ALPHANUMEXT);
$fromformdata['educationallevel'] = optional_param('educationallevel', 'all', PARAM_ALPHANUMEXT);
$fromformdata['downloadable'] = optional_param('downloadable', $usercandownload, PARAM_ALPHANUM);
$fromformdata['orderby'] = optional_param('orderby', 'newest', PARAM_ALPHA);
$fromformdata['huburl'] = optional_param('huburl', $registeredhub->huburl /*HUB_MOODLEORGHUBURL*/, PARAM_URL);
$fromformdata['search'] = $search;
// $fromformdata['courseid'] = $courseid;
$hubselectorform = new \block_customhub\form\block_customhub_searchpage_form('', $fromformdata);
$hubselectorform->set_data($fromformdata);

// Retrieve courses by web service
$courses = null;
if (optional_param('executesearch', 0, PARAM_INT) and confirm_sesskey()) {
    $downloadable = optional_param('downloadable', false, PARAM_INT);

    $options = new stdClass();
    if (!empty($fromformdata['coverage'])) {
        $options->coverage = $fromformdata['coverage'];
    }
    if ($fromformdata['licence'] != 'all') {
        $options->licenceshortname = $fromformdata['licence'];
    }
    if ($fromformdata['subject'] != 'all') {
        $options->subject = $fromformdata['subject'];
    }
    if ($fromformdata['audience'] != 'all') {
        $options->audience = $fromformdata['audience'];
    }
    if ($fromformdata['educationallevel'] != 'all') {
        $options->educationallevel = $fromformdata['educationallevel'];
    }
    if ($fromformdata['language'] != 'all') {
        $options->language = $fromformdata['language'];
    }

    $options->orderby = $fromformdata['orderby'];

    //the range of course requested
    $options->givememore = optional_param('givememore', 0, PARAM_INT);
    //check if the selected hub is from the registered list (in this case we use the private token)
    $token = 'publichub';

    $function = 'hub_get_courses';
    $params = [
        'search' => $search,
        'downloadable' => $downloadable,
        'enrollable' => intval(!$downloadable),
        'options' => $options
    ];
    $serverurl = $registeredhub->huburl . "/local/hub/webservice/webservices.php";

    require_once($CFG->dirroot . "/webservice/xmlrpc/lib.php");
    $xmlrpcclient = new webservice_xmlrpc_client($serverurl, $registeredhub->token);
    try {
        $result = $xmlrpcclient->call($function, array_values($params));
        $courses = $result['courses'];
        $SESSION->hubcourselist = $customhubhelper->set_courseregid_as_key($courses);
        $coursetotal = $result['coursetotal'];
    } catch (Exception $e) {
        $errormessage = $OUTPUT->notification(
            get_string('errorcourselisting', 'block_customhub', $e->getMessage())
        );
    }
}


// OUTPUT
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('searchcommunitycourse', 'block_customhub'), 3, 'main');
$hubselectorform->display();
if (!empty($errormessage)) {
    echo $errormessage;
}

//load javascript
$commentedcourseids = []; //result courses with comments only
$courseids = []; //all result courses
$courseimagenumbers = []; //number of screenshots of all courses (must be exact same order than $courseids)
if (!empty($courses)) {
    foreach ($courses as $course) {
        if (!empty($course['comments'])) {
            $commentedcourseids[] = $course['id'];
        }
        $courseids[] = $course['id'];
        $courseimagenumbers[] = $course['screenshots'];
    }
}
$PAGE->requires->yui_module(
    'moodle-block_customhub-comments',
    'M.blocks_customhub.init_comments',
    [[
        'commentids' => $commentedcourseids,
        'closeButtonTitle' => get_string('close', 'editor')
    ]]
);
$PAGE->requires->yui_module(
    'moodle-block_customhub-imagegallery',
    'M.blocks_customhub.init_imagegallery',
    [[
        'imageids' => $courseids, 'imagenumbers' => $courseimagenumbers,
        'huburl' => $registeredhub->huburl, 'closeButtonTitle' => get_string('close', 'editor')
    ]]
);

if (!empty($courses)) {

    $coursecards = [];
    foreach ($courses as $course) {
        $course = (object)$course;
        $downloadable = !$course->enrollable;
        $enrolable = $course->enrollable;
        $coursecard = new stdClass();
        $coursecard->fullname = $course->fullname;
        $coursecard->shortname = $course->shortname;
        $coursecard->description = $course->description;
        $coursecard->language = $course->language;
        $coursecard->creatorname = $course->creatorname;
        $coursecard->rating_count = $course->rating['count'];
        // Pass rating classes.
        $ratingclass = \block_mbsteachshare\search::get_rating_stars_classes($course->rating['count']);
        $coursecard->starclass_1  = $ratingclass[1];
        $coursecard->starclass_2  = $ratingclass[2];
        $coursecard->starclass_3  = $ratingclass[3];
        $coursecard->starclass_4  = $ratingclass[4];
        $coursecard->starclass_5  = $ratingclass[5];

        // $coursecard->rating_avg = $course->rating;
        if ($downloadable) {
            $coursecard->courseurl = $course->courseurl;
            $params = [
                'sesskey' => sesskey(),
                'download' => 1,
                'confirmed' => 1,
                // 'remotemoodleurl' => $CFG->wwwroot,
                'downloadcourseid' => $course->id,
                'reqsource' => 1,
                // 'huburl' => $huburl,
                'coursefullname' => $course->fullname/*, 'backupsize' => $course->backupsize*/
            ];
            $downloadurl = new moodle_url("/blocks/customhub/communitysearch.php", $params);
            $coursecard->installurl = $downloadurl;
        } 
        
        if ($enrolable) {
            $params = [
                'sesskey' => sesskey(),
                'add' => 1,
                'confirmed' => 1,
                'reqsource' => 1,
                'crid' => $course->id,
            ];
            $enrolurl = new moodle_url("/blocks/customhub/communitysearch.php", $params);
            $coursecard->enrolurl = $enrolurl;
        }

        // Collect screenshots.
        $screenshothtml = '';
        if (empty($course->screenshots)){
            $dummyurl = "https://mbsmoodle.localhost/theme/image.php/mebis/theme_boost_campus/1643043364/mebis_diamond_reverse";
            $screenshothtml = html_writer::empty_tag(
                'img',
                [
                    'src' => $dummyurl,
                    'alt' => $course->fullname,
                ]
            );
        }
        if (!empty($course->screenshots)) {

            // $baseurl = new moodle_url(
            //     $huburl . '/local/hub/webservice/download.php',
            //     [
            //         'courseid' => $course->id,
            //         'filetype' => HUB_SCREENSHOT_FILE_TYPE
            //     ]
            // );
            // $screenshothtml = html_writer::empty_tag(
            //     'img',
            //     [
            //         'src' => $baseurl,
            //         'alt' => $course->fullname]
            // );

        }
        $coursescreenshot = html_writer::tag(
            'div',
            $screenshothtml,
            [
                'class' => 'coursescreenshot',
                'id' => 'image-' . $course->id
            ]
        );
        $coursecard->screenshot = $screenshothtml;

        $coursecards[] = $renderer->render_from_template('block_customhub/coursecard', $coursecard);
    }
    $coursecards = join('', $coursecards);
    $coursecardlist = new stdClass();
    $coursecardlist->coursecards = $coursecards;

    echo $renderer->render_from_template('block_customhub/coursecardlist', $coursecardlist);
}

//display givememore/Next link if more course can be displayed
if (!empty($courses)) {
    if (($options->givememore + count($courses)) < $coursetotal) {
        $fromformdata['givememore'] = count($courses) + $options->givememore;
        $fromformdata['executesearch'] = true;
        $fromformdata['sesskey'] = sesskey();
        echo $renderer->next_button($fromformdata);
    }
}

echo $OUTPUT->footer();