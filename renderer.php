<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Block customhub renderer.
 * @package   block_customhub
 * @copyright 2010 Moodle Pty Ltd (http://moodle.com)
 * @author    Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_customhub_renderer extends plugin_renderer_base {

    public function restore_confirmation_box($filename, $context) {
        $restoreurl = new moodle_url(
            '/backup/restore.php',
            ['filename' => $filename . ".mbz", 'contextid' => $context->id]
        );
        $searchurl = new moodle_url(
            '/blocks/customhub/communitycourse.php',
            [
                'add' => 1, 'courseid' => $context->instanceid,
                'cancelrestore' => 1, 'sesskey' => sesskey(),
                'filename' => $filename
            ]
        );
        $formrestore = new single_button(
            $restoreurl,
            get_string('dorestore', 'block_customhub')
        );
        $formsearch = new single_button(
            $searchurl,
            get_string('donotrestore', 'block_customhub')
        );
        return $this->output->confirm(
            get_string('restorecourseinfo', 'block_customhub'),
            $formrestore,
            $formsearch
        );
    }

    /**
     * Display remove community success message and a button to be redirected to te referer page
     * @param moodle_url $url the page to be redirected to
     * @return string html
     */
    public function remove_success(moodle_url $url) {
        $html = $this->output->notification(get_string('communityremoved', 'block_customhub'),
                    'notifysuccess');
        $continuebutton = new single_button($url,
                        get_string('continue', 'block_customhub'));
        $html .= html_writer::tag('div', $this->output->render($continuebutton),
                array('class' => 'continuebutton'));
        return $html;
    }

    /**
     * Display add community course success message and a button to be redirected to te referer page
     * @param moodle_url $url the page to be redirected to
     * @return string html
     */
    public function save_link_success(moodle_url $url) {
        $html = $this->output->notification(get_string('addedtoblock', 'block_customhub'),
                    'notifysuccess');
        $continuebutton = new single_button($url,
                        get_string('continue', 'block_customhub'));
        $html .= html_writer::tag('div', $this->output->render($continuebutton),
                array('class' => 'continuebutton'));
        return $html;
    }

    /**
     * The 'Next'/'more course result' link for a courses search
     * @param array $data - the form parameter to execute the search on more result
     * @return string html code
     */
    public function next_button($data) {
        $nextlink = html_writer::tag('a', get_string('next', 'block_customhub'),
                array('href' => new moodle_url('', $data)));
        return html_writer::tag('div', $nextlink, array( 'class' => 'nextlink'));
    }

    /**
     * Display a list of courses
     * @param array $courses
     * @param boolean $withwriteaccess
     * @param int $contextcourseid context course id
     * @return string
     */
    public function course_list($courses, $huburl, $contextcourseid) {
        global $CFG;

        $renderedhtml = '';

        if (empty($courses)) {
            if (isset($courses)) {
                $renderedhtml .= get_string('nocourse', 'block_customhub');
            }
        } else {
            $courseiteration = 0;
            foreach ($courses as $course) {
                $course = (object) $course;
                $courseiteration = $courseiteration + 1;

                //create visit link html
                if (!empty($course->courseurl)) {
                    $courseurl = new moodle_url($course->courseurl);
                    $linktext = get_string('visitsite', 'block_customhub');
                } else {
                    $courseurl = new moodle_url($course->demourl);
                    $linktext = get_string('visitdemo', 'block_customhub');
                }

                $visitlinkhtml = html_writer::tag('a', $linktext,
                                array('href' => $courseurl, 'class' => 'hubcoursedownload',
                                    'onclick' => 'this.target="_blank"'));

                //create title html
                $coursename = html_writer::tag('h3', $course->fullname,
                                array('class' => 'hubcoursetitle'));
                $coursenamehtml = html_writer::tag('div', $coursename,
                        array('class' => 'hubcoursetitlepanel'));

                // create screenshots html
                $screenshothtml = '';
                if (!empty($course->screenshots)) {
                    $baseurl = new moodle_url($huburl . '/local/hub/webservice/download.php',
                                    array('courseid' => $course->id,
                                        'filetype' => HUB_SCREENSHOT_FILE_TYPE));
                    $screenshothtml = html_writer::empty_tag('img',
                        array('src' => $baseurl, 'alt' => $course->fullname));
                }
                $coursescreenshot = html_writer::tag('div', $screenshothtml,
                                array('class' => 'coursescreenshot',
                                    'id' => 'image-' . $course->id));

                //create description html
                $deschtml = html_writer::tag('div', $course->description,
                                array('class' => 'hubcoursedescription'));

                //create users related information html
                $courseuserinfo = get_string('userinfo', 'block_customhub', $course);
                if ($course->contributornames) {
                    $courseuserinfo .= ' - ' . get_string('contributors', 'block_customhub',
                                    $course->contributornames);
                }
                $courseuserinfohtml = html_writer::tag('div', $courseuserinfo,
                                array('class' => 'hubcourseuserinfo'));

                //create course content related information html
                $course->subject = (get_string_manager()->string_exists($course->subject, 'edufields')) ?
                        get_string($course->subject, 'edufields') : get_string('none');
                $course->audience = get_string('audience' . $course->audience, 'tool_customhub');
                $course->educationallevel = get_string('edulevel' . $course->educationallevel, 'tool_customhub');
                $coursecontentinfo = '';
                if (empty($course->coverage)) {
                    $course->coverage = '';
                } else {
                    $coursecontentinfo .= get_string('coverage', 'block_customhub', $course->coverage);
                    $coursecontentinfo .= ' - ';
                }
                $coursecontentinfo .= get_string('contentinfo', 'block_customhub', $course);
                $coursecontentinfohtml = html_writer::tag('div', $coursecontentinfo,
                                array('class' => 'hubcoursecontentinfo'));

                ///create course file related information html
                //language
                if (!empty($course->language)) {
                    $languages = get_string_manager()->get_list_of_languages();
                    $course->lang = $languages[$course->language];
                } else {
                    $course->lang = '';
                }
                //licence
                require_once($CFG->libdir . "/licenselib.php");
                $licensemanager = new license_manager();
                $licenses = $licensemanager->get_licenses();
                foreach ($licenses as $license) {
                    if ($license->shortname == $course->licenceshortname) {
                        $course->license = $license->fullname;
                    }
                }
                $course->timeupdated = userdate($course->timemodified);
                $coursefileinfo = get_string('fileinfo', 'block_customhub', $course);
                $coursefileinfohtml = html_writer::tag('div', $coursefileinfo,
                                array('class' => 'hubcoursefileinfo'));


                $blocksandactivities = "";
                //Create course content html
                $blocks = core_component::get_plugin_list('block');
                $activities = core_component::get_plugin_list('mod');
                if (!empty($course->contents)) {
                    $activitieshtml = '';
                    $blockhtml = '';
                    foreach ($course->contents as $content) {
                        $content = (object) $content;
                        if ($content->moduletype == 'block') {
                            if (!empty($blockhtml)) {
                                $blockhtml .= ' - ';
                            }
                            if (array_key_exists($content->modulename, $blocks)) {
                                $blockname = get_string('pluginname', 'block_' . $content->modulename);
                            } else {
                                $blockname = $content->modulename;
                            }
                            $blockhtml .= $blockname . " (" . $content->contentcount . ")";
                        } else {
                            if (!empty($activitieshtml)) {
                                $activitieshtml .= ' - ';
                            }
                            if (array_key_exists($content->modulename, $activities)) {
                                $activityname = get_string('modulename', $content->modulename);
                            } else {
                                $activityname = $content->modulename;
                            }
                            $activitieshtml .= $activityname . " (" . $content->contentcount . ")";
                        }
                    }

                    $blocksandactivities = html_writer::tag('div',
                                    get_string('activities', 'block_customhub') . " : " . $activitieshtml);

                    //Uncomment following lines to display blocks information
//                    $blocksandactivities .= html_writer::tag('span',
//                                    get_string('blocks', 'block_customhub') . " : " . $blockhtml);
                }

                //Create outcomes html
                $outcomes= '';
                if (!empty($course->outcomes)) {
                    foreach ($course->outcomes as $outcome) {
                        if (!empty($outcomes)) {
                            $outcomes .= ', ';
                        }
                        $outcomes .= $outcome['fullname'];
                    }
                    $outcomes = get_string('outcomes', 'block_customhub',
                            $outcomes);
                }
                $outcomeshtml = html_writer::tag('div', $outcomes, array('class' => 'hubcourseoutcomes'));

                //create additional information html
                $additionaldesc = $courseuserinfohtml . $coursecontentinfohtml
                        . $coursefileinfohtml . $blocksandactivities . $outcomeshtml;
                $additionaldeschtml = html_writer::tag('div', $additionaldesc,
                                array('class' => 'additionaldesc'));

                //Create add button html
                $addbuttonhtml = "";
                if ($course->enrollable) {
                    $params = [
                        'sesskey' => sesskey(),
                        'add' => 1,
                        'confirmed' => 1,
                        'courseid' => $contextcourseid,
                        // 'coursefullname' => $course->fullname,
                        // 'courseurl' => $courseurl,
                        // 'coursedescription' => $course->description,
                        'crid' => $course->id,
                    ];

                    $addurl = new moodle_url("/blocks/customhub/communitycourse.php", $params);
                    $addbuttonhtml = html_writer::tag(
                        'a',
                        get_string('addtocommunityblock', 'block_customhub'),
                        [
                            'href' => $addurl,
                            'class' => 'centeredbutton, hubcoursedownload'
                        ]
                    );
                }

                //create download button html
                $downloadbuttonhtml = "";
                if (!$course->enrollable) {
                    $params = array('sesskey' => sesskey(), 'download' => 1, 'confirmed' => 1,
                        'remotemoodleurl' => $CFG->wwwroot, 'courseid' => $contextcourseid,
                        'downloadcourseid' => $course->id, 'huburl' => $huburl,
                'coursefullname' => $course->fullname/*, 'backupsize' => $course->backupsize*/);
                    $downloadurl = new moodle_url("/blocks/customhub/communitycourse.php", $params);
                    $downloadbuttonhtml = html_writer::tag('a', get_string('install', 'block_customhub'),
                                    array('href' => $downloadurl, 'class' => 'centeredbutton, hubcoursedownload'));
                }

                //Create rating html
                $rating = html_writer::tag('div', get_string('noratings', 'block_customhub'),
                                array('class' => 'norating'));
                if (!empty($course->rating)) {
                    $course->rating = (object) $course->rating;
                    if ($course->rating->count > 0) {

                        //calculate size of the rating star
                        $starimagesize = 20; //in px
                        $numberofstars = 5;
                        $size = ($course->rating->aggregate / $course->rating->scaleid)
                                * $numberofstars * $starimagesize;
                        $rating = html_writer::tag('li', '',
                                        array('class' => 'current-rating',
                                            'style' => 'width:' . $size . 'px;'));

                        $rating = html_writer::tag('ul', $rating,
                                        array('class' => 'star-rating clearfix'));
                        $rating .= html_writer::tag('div', ' (' . $course->rating->count . ')',
                                        array('class' => 'ratingcount clearfix'));
                    }
                }


                //Create comments html
                $coursecomments = "";
                // $coursecomments = html_writer::tag('div', get_string('nocomments', 'block_customhub'),
                //                 array('class' => 'nocomments'));
                // $commentcount = 0;
                // if (!empty($course->comments)) {
                //     //display only if there is some comment if there is some comment
                //     $commentcount = count($course->comments);
                //     $coursecomments = html_writer::tag('div',
                //                     get_string('comments', 'block_customhub', $commentcount),
                //                     array('class' => 'commenttitle'));

                //     foreach ($course->comments as $comment) {
                //         $commentator = html_writer::tag('div',
                //                         $comment['commentator'],
                //                         array('class' => 'hubcommentator'));
                //         $commentdate = html_writer::tag('div',
                //                         ' - ' . userdate($comment['date'], '%e/%m/%y'),
                //                         array('class' => 'hubcommentdate clearfix'));

                //         $commenttext = html_writer::tag('div',
                //                         $comment['comment'],
                //                         array('class' => 'hubcommenttext'));

                //         $coursecomments .= html_writer::tag('div',
                //                         $commentator . $commentdate . $commenttext,
                //                         array('class' => 'hubcomment'));
                //     }
                //     $coursecommenticon = html_writer::tag('div',
                //                     get_string('comments', 'block_customhub', $commentcount),
                //                     array('class' => 'hubcoursecomments',
                //                         'id' => 'comments-' . $course->id));
                //     $coursecomments = $coursecommenticon . html_writer::tag('div',
                //                     $coursecomments,
                //                     array('class' => 'yui3-overlay-loading',
                //                         'id' => 'commentoverlay-' . $course->id));
                // }

                //link rate and comment
                $rateandcomment = "";
                // $rateandcomment = html_writer::tag('div',
                //                 html_writer::tag('a', get_string('rateandcomment', 'block_customhub'),
                //                         array('href' => new moodle_url($huburl,
                //                                     array('courseid' => $course->id, 'mustbelogged' => true)),
                //                             'onclick' => 'this.target="_blank"')),
                //                 array('class' => 'hubrateandcomment'));

                //the main DIV tags
                $buttonsdiv = html_writer::tag('div',
                                $addbuttonhtml . $downloadbuttonhtml . $visitlinkhtml,
                                array('class' => 'courseoperations'));
                $screenshotbuttonsdiv = html_writer::tag('div',
                                $coursescreenshot . $buttonsdiv,
                                array('class' => 'courselinks'));

                $coursedescdiv = html_writer::tag('div',
                                $deschtml . $additionaldeschtml
                                . $rating . $coursecomments . $rateandcomment,
                                array('class' => 'coursedescription'));
                $coursehtml =
                        $coursenamehtml . html_writer::tag('div',
                                $coursedescdiv . $screenshotbuttonsdiv,
                                array('class' => 'hubcourseinfo clearfix'));

                $renderedhtml .=html_writer::tag('div', $coursehtml,
                                array('class' => 'fullhubcourse clearfix'));
            }

            $renderedhtml = html_writer::tag('div', $renderedhtml,
                            array('class' => 'hubcourseresult'));
        }

        return $renderedhtml;
    }

}
