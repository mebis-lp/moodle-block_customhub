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
 * Form for community search
 *
 * @package    block_customhub
 * @author     Jerome Mouneyrac <jerome@mouneyrac.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 */

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/customhub/constants.php');
require_once($CFG->dirroot . '/blocks/customhub/hublisting.php');

class block_customhub_search_form extends moodleform {

    const BLOCK_CUSTOMHUB_HUB_HUBDIRECTORYURL = 'https://hubdirectory.moodle.org';

    protected function get_standad_hubs_list() {
        return $this->get_standard_hubs_list_from_hubdirectory();
        // This will return something like this.
        /*
        return [[
            'id' => 62,
            'name' => 'Moodle.net',
            'description' => 'Moodle.net (previously known as MOOCH) connects you with free content and courses shared by Moodle users all over the world.  It contains:

* courses you can download and use
* courses you can enrol in and participate
* other content you can import into your own courses',
            'language' => 'en',
            'url' => 'https://moodle.net',
            'trusted' => 1,
            'sites' => 187801,
            'courses' => 290,
            'timemodified' => 1416283567,
            'enrollablecourses' => 152,
            'downloadablecourses' => 138,
            'smalllogohtml' => '<img src="https://hubdirectory.moodle.org/local/hubdirectory/webservice/download.php?hubid=62&amp;filetype=hubscreenshot" alt="Moodle.net" height="30" width="40" />',
            'hubimage' => '<div class="hubimage"><img src="https://hubdirectory.moodle.org/local/hubdirectory/webservice/download.php?hubid=62&amp;filetype=hubscreenshot" alt="Moodle.net" /></div>'
        ]];
        */
    }

    protected function get_standard_hubs_list_from_hubdirectory() {
        global $CFG, $OUTPUT;

        //retrieve the hub list on the hub directory by web service
        $function = 'hubdirectory_get_hubs';
        $params = array();
        $serverurl = self::BLOCK_CUSTOMHUB_HUB_HUBDIRECTORYURL. "/local/hubdirectory/webservice/webservices.php";
        require_once($CFG->dirroot . "/webservice/xmlrpc/lib.php");
        $xmlrpcclient = new webservice_xmlrpc_client($serverurl, 'publichubdirectory');
        try {
            $hubs = $xmlrpcclient->call($function, $params);
        } catch (Exception $e) {
            $hubs = array();
            $error = $OUTPUT->notification(get_string('errorhublisting', 'block_customhub', $e->getMessage()));
            $this->_form->addElement('static', 'errorhub', '', $error);
        }

        foreach ($hubs as $key => $hub) {
            if ($hub['url'] === HUB_OLDMOODLEORGHUBURL) {
                // Hubdirectory returns old URL for the moodle.net hub, substitute it.
                $hubs[$key]['url'] = HUB_MOODLEORGHUBURL;
            }

            // Retrieve hub logo + generate small logo.
            $params = array('hubid' => $hub['id'], 'filetype' => HUB_HUBSCREENSHOT_FILE_TYPE);
            $imgurl = new moodle_url(self::BLOCK_CUSTOMHUB_HUB_HUBDIRECTORYURL . "/local/hubdirectory/webservice/download.php", $params);

            // The following code is commented out because extra requests slow down the page rendering. Assume all images are present.

            //$imgsize = getimagesize($imgurl->out(false));
            //$ascreenshothtml = '';
            //if ($imgsize[0] > 1) {
                $ascreenshothtml = html_writer::empty_tag('img', array('src' => $imgurl, 'alt' => $hub['name']));
                $hubs[$key]['smalllogohtml'] = html_writer::empty_tag('img', array('src' => $imgurl, 'alt' => $hub['name'],
                    'height' => 30, 'width' => 40));
            //}
            $hubs[$key]['hubimage'] = html_writer::tag('div', $ascreenshothtml, array('class' => 'hubimage'));

        }

        return $hubs;
    }

    public function definition() {
        global $CFG, $USER, $OUTPUT;
        $strrequired = get_string('required');
        $mform = & $this->_form;

        //set default value
        $search = $this->_customdata['search'];
        if (isset($this->_customdata['coverage'])) {
            $coverage = $this->_customdata['coverage'];
        } else {
            $coverage = 'all';
        }
        if (isset($this->_customdata['licence'])) {
            $licence = $this->_customdata['licence'];
        } else {
            $licence = 'all';
        }
        if (isset($this->_customdata['subject'])) {
            $subject = $this->_customdata['subject'];
        } else {
            $subject = 'all';
        }
        if (isset($this->_customdata['audience'])) {
            $audience = $this->_customdata['audience'];
        } else {
            $audience = 'all';
        }
        if (isset($this->_customdata['language'])) {
            $language = $this->_customdata['language'];
        } else {
            $language = current_language();
        }
        if (isset($this->_customdata['educationallevel'])) {
            $educationallevel = $this->_customdata['educationallevel'];
        } else {
            $educationallevel = 'all';
        }
        if (isset($this->_customdata['downloadable'])) {
            $downloadable = $this->_customdata['downloadable'];
        } else {
            $downloadable = 1;
        }
        if (isset($this->_customdata['orderby'])) {
            $orderby = $this->_customdata['orderby'];
        } else {
            $orderby = 'newest';
        }
        if (isset($this->_customdata['huburl'])) {
            $huburl = $this->_customdata['huburl'];
        } else {
            $huburl = HUB_MOODLEORGHUBURL;
        }

        $mform->addElement('header', 'site', get_string('search', 'block_customhub'));

        //add the course id (of the context)
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'executesearch', 1);
        $mform->setType('executesearch', PARAM_INT);

        // Retrieve list of standard hubs (used to be requested from hubdirectory but now hardcoded).
        $hubs = $this->get_standad_hubs_list();

        //display list of registered on hub
        $registrationmanager = new tool_customhub\registration_manager();
        $registeredhubs = $registrationmanager->get_registered_on_hubs();
        //retrieve some additional hubs that we will add to
        //the hub list got from the hub directory
        $additionalhubs = array();
        foreach ($registeredhubs as $registeredhub) {
            $inthepubliclist = false;
            foreach ($hubs as $hub) {
                if ($hub['url'] == $registeredhub->huburl) {
                    $inthepubliclist = true;
                    $hub['registeredon'] = true;
                }
            }
            if (!$inthepubliclist) {
                $additionalhub = array();
                $additionalhub['name'] = $registeredhub->hubname;
                $additionalhub['url'] = $registeredhub->huburl;
                $additionalhubs[] = $additionalhub;
            }
        }
        if (!empty($additionalhubs)) {
            $hubs = array_merge($hubs, $additionalhubs);
        }

        if (!empty($hubs)) {
            $htmlhubs = array();
            foreach ($hubs as $hub) {
                // Name can come from hub directory - need some cleaning.
                $hubname = clean_text($hub['name'], PARAM_TEXT);
                $smalllogohtml = '';
                if (array_key_exists('id', $hub)) {

                    $smolllogohtml = isset($hub['smalllogohtml']) ? $hub['smalllogohtml'] : '';
                    $hubimage = isset($hub['hubimage']) ? $hub['hubimage'] : '';

                    // Statistics + trusted info.
                    $hubstats = '';
                    if (isset($hub['enrollablecourses'])) { //check needed to avoid warnings for Moodle version < 2011081700
                        $additionaldesc = get_string('enrollablecourses', 'block_customhub') . ': ' . $hub['enrollablecourses'] . ' - ' .
                                get_string('downloadablecourses', 'block_customhub') . ': ' . $hub['downloadablecourses'];
                        $hubstats .= html_writer::tag('div', $additionaldesc);
                    }
                    if ($hub['trusted']) {
                        $hubtrusted =  get_string('hubtrusted', 'block_customhub');
                        $hubstats .= $OUTPUT->doc_link('trusted_hubs') . html_writer::tag('div', $hubtrusted);
                    }
                    $hubstats = html_writer::tag('div', $hubstats, array('class' => 'hubstats'));

                    // hub name link + hub description.
                    $hubnamelink = html_writer::link($hub['url'], html_writer::tag('h2',$hubname),
                                    array('class' => 'hubtitlelink'));
                    // The description can come from the hub directory - need to clean.
                    $hubdescription = clean_param($hub['description'], PARAM_TEXT);
                    $hubdescriptiontext = html_writer::tag('div', format_text($hubdescription, FORMAT_PLAIN),
                                    array('class' => 'hubdescription'));

                    $hubtext = html_writer::tag('div', $hubdescriptiontext . $hubstats, array('class' => 'hubtext'));

                    $hubimgandtext = html_writer::tag('div', $hubimage . $hubtext, array('class' => 'hubimgandtext'));

                    $hubfulldesc = html_writer::tag('div', $hubnamelink . $hubimgandtext, array('class' => 'hubmainhmtl'));
                } else {
                    $hubfulldesc = html_writer::link($hub['url'], $hubname);
                }

                // Add hub to the hub items.
                $hubinfo = new stdClass();
                $hubinfo->mainhtml = $hubfulldesc;
                $hubinfo->rowhtml = html_writer::tag('div', $smalllogohtml , array('class' => 'hubsmalllogo')) . $hubname;
                $hubitems[$hub['url']] = $hubinfo;
            }

            // Hub listing form element.
            $mform->addElement('customhublisting','huburl', '', '', array('items' => $hubitems,
                'showall' => get_string('showall', 'block_customhub'),
                'hideall' => get_string('hideall', 'block_customhub')));
            $mform->setDefault('huburl', $huburl);

            //display enrol/download select box if the USER has the download capability on the course
            if (has_capability('block/customhub:downloadcommunity',
                            context_course::instance($this->_customdata['courseid']))) {
                $options = array(0 => get_string('enrollable', 'block_customhub'),
                    1 => get_string('downloadable', 'block_customhub'));
                $mform->addElement('select', 'downloadable', get_string('enroldownload', 'block_customhub'),
                        $options);
                $mform->addHelpButton('downloadable', 'enroldownload', 'block_customhub');

                $mform->setDefault('downloadable', $downloadable);
            } else {
                $mform->addElement('hidden', 'downloadable', 0);
            }
            $mform->setType('downloadable', PARAM_INT);

            $options = array();
            $options['all'] = get_string('any');
            $options[HUB_AUDIENCE_EDUCATORS] = get_string('audienceeducators', 'tool_customhub');
            $options[HUB_AUDIENCE_STUDENTS] = get_string('audiencestudents', 'tool_customhub');
            $options[HUB_AUDIENCE_ADMINS] = get_string('audienceadmins', 'tool_customhub');
            $mform->addElement('select', 'audience', get_string('audience', 'block_customhub'), $options);
            $mform->setDefault('audience', $audience);
            unset($options);
            $mform->addHelpButton('audience', 'audience', 'block_customhub');

            $options = array();
            $options['all'] = get_string('any');
            $options[HUB_EDULEVEL_PRIMARY] = get_string('edulevelprimary', 'tool_customhub');
            $options[HUB_EDULEVEL_SECONDARY] = get_string('edulevelsecondary', 'tool_customhub');
            $options[HUB_EDULEVEL_TERTIARY] = get_string('eduleveltertiary', 'tool_customhub');
            $options[HUB_EDULEVEL_GOVERNMENT] = get_string('edulevelgovernment', 'tool_customhub');
            $options[HUB_EDULEVEL_ASSOCIATION] = get_string('edulevelassociation', 'tool_customhub');
            $options[HUB_EDULEVEL_CORPORATE] = get_string('edulevelcorporate', 'tool_customhub');
            $options[HUB_EDULEVEL_OTHER] = get_string('edulevelother', 'tool_customhub');
            $mform->addElement('select', 'educationallevel',
                    get_string('educationallevel', 'block_customhub'), $options);
            $mform->setDefault('educationallevel', $educationallevel);
            unset($options);
            $mform->addHelpButton('educationallevel', 'educationallevel', 'block_customhub');

            $publicationmanager = new \tool_customhub\course_publish_manager();
            $options = $publicationmanager->get_sorted_subjects();
            $mform->addElement('searchableselector', 'subject', get_string('subject', 'block_customhub'),
                    $options, array('id' => 'communitysubject'));
            $mform->setDefault('subject', $subject);
            unset($options);
            $mform->addHelpButton('subject', 'subject', 'block_customhub');

            require_once($CFG->libdir . "/licenselib.php");
            $licensemanager = new license_manager();
            $licences = $licensemanager->get_licenses();
            $options = array();
            $options['all'] = get_string('any');
            foreach ($licences as $license) {
                $options[$license->shortname] = get_string($license->shortname, 'license');
            }
            $mform->addElement('select', 'licence', get_string('licence', 'block_customhub'), $options);
            unset($options);
            $mform->addHelpButton('licence', 'licence', 'block_customhub');
            $mform->setDefault('licence', $licence);

            $languages = get_string_manager()->get_list_of_languages();
            core_collator::asort($languages);
            $languages = array_merge(array('all' => get_string('any')), $languages);
            $mform->addElement('select', 'language', get_string('language'), $languages);

            $mform->setDefault('language', $language);
            $mform->addHelpButton('language', 'language', 'block_customhub');

            $mform->addElement('select', 'orderby', get_string('orderby', 'block_customhub'),
                array('newest' => get_string('orderbynewest', 'block_customhub'),
                    'eldest' => get_string('orderbyeldest', 'block_customhub'),
                    'fullname' => get_string('orderbyname', 'block_customhub'),
                    'publisher' => get_string('orderbypublisher', 'block_customhub'),
                    'ratingaverage' => get_string('orderbyratingaverage', 'block_customhub')));

            $mform->setDefault('orderby', $orderby);
            $mform->addHelpButton('orderby', 'orderby', 'block_customhub');
            $mform->setType('orderby', PARAM_ALPHA);

            $mform->setAdvanced('audience');
            $mform->setAdvanced('educationallevel');
            $mform->setAdvanced('subject');
            $mform->setAdvanced('licence');
            $mform->setAdvanced('language');
            $mform->setAdvanced('orderby');

            $mform->addElement('text', 'search', get_string('keywords', 'block_customhub'),
                array('size' => 30));
            $mform->addHelpButton('search', 'keywords', 'block_customhub');
            $mform->setType('search', PARAM_NOTAGS);

            $mform->addElement('submit', 'submitbutton', get_string('search', 'block_customhub'));
        }
    }

    function validation($data, $files) {
        global $CFG;

        $errors = array();

        if (empty($this->_form->_submitValues['huburl'])) {
            $errors['huburl'] = get_string('nohubselected', 'block_customhub');
        }

        return $errors;
    }

}
