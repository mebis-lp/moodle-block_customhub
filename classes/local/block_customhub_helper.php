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
 * Helper class for block_customhub.
 *
 * @package     block_customhub
 * @copyright   2022, ISB Bayern
 * @author      Peter Mayer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

namespace block_customhub\local;

defined('MOODLE_INTERNAL') || die();

// require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/customhub/constants.php');
// require_once($CFG->dirroot . '/blocks/customhub/hublisting.php');

/**
 * Helper class for block_customhub.
 *
 * @package     block_customhub
 * @copyright   2022, ISB Bayern
 * @author      Peter Mayer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class block_customhub_helper {

    /* Constant for Category name */
    const COLLABORATION_COURSECATEGORY_NAME = 'Zusammenarbeit';

    /* Constant for Category name */
    const COURSE_FORMAT = 'singleactivity';

    /* Use mod_lti plugin */
    const SINGLE_ACTIVITY_COURSE_MODULE = 'lti';

    public function create_collaboration_course($coursedata) {
        $ccat = $this->create_collaboration_course_category();
        $course = $this->create_course($ccat, $coursedata);
        $this->create_enrolment($course, 'manual');
    }

    /**
     * Create a Demo Course Category if necessary.
     * @return $object course_category record
     */
    public function create_collaboration_course_category() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/testing/generator/data_generator.php');

        $coursecat = $DB->get_record('course_categories', ['name' => self::COLLABORATION_COURSECATEGORY_NAME]);

        if (empty($coursecat)) {
            $generator = new \testing_data_generator();
            $record = [
                'name' => self::COLLABORATION_COURSECATEGORY_NAME,
                'parent' => 0,
                'descriptionformat' => 0,
                'visible' => 1,
                'description' => '',
            ];
            $coursecat = $generator->create_category($record);
        }
        return $coursecat;
    }
    
    public function create_course($ccat, $coursedata) {
        global $CFG;
        require_once($CFG->libdir . '/testing/generator/data_generator.php');
        $generator = new \testing_data_generator();

        $courserecord = [
            'shortname' => $this->get_unused_shortname($coursedata->shortname),
            'fullname' => $coursedata->fullname,
            'category' => $ccat->id,
            'format' => self::COURSE_FORMAT,
            'activitytype' => self::SINGLE_ACTIVITY_COURSE_MODULE,
            'summary' => $coursedata->description,
            'idnumber' => $coursedata->id,
            'enablecompletion' => 1,
        ];

        // Create the local course if not exists.
        if ($course = $DB->get_record('{course}', ['idnumber' => $idnumber])) {
            $course = $generator->create_course($courserecord, ['createsections' => true]);
        }

        return $course;
    }

    public function get_course_registration_data($courseregid) {
        
    }

    public function set_courseregid_as_key($courslist) {
        $courses = [];
        foreach($courslist as $course){
            $courses[$course['id']] = (object)$course;
        }
        return $courses;
    }


    // public function create_mod_lti($course, $url, $pwd) {
    //         /**
    //  * Create mod_label
    //  * @param object $course
    //  * @param array $result
    //  * @param string $intro
    //  */
    //     $generator = \core\testing\generator::instance();
    //     $modgenerator = $generator->get_plugin_generator('mod_label');
    //     $record = [
    //         'course' => $course->id,
    //         'intro' => $intro,
    //         'idnumber' => $result['external_id'],
    //         'section' => $result['metadata']['supplementary_content'],
    //     ];
    //     $modgenerator->create_instance($record);
    // }

    /**
     * Get unused Shortname
     * @param string $shortname
     * @return string unused shortname
     */
    public static function get_unused_shortname($shortname) {
        global $DB;
        $seperator = "";
        $i = "";
        while (!empty($DB->get_record('course', ['shortname' => $shortname . $seperator . $i]))) {
            $i++;
            $seperator = "_";
        }
        return $shortname . $seperator . $i;
    }

    private function course_exists($idnumber) { 
        global $DB;
        return $DB->record_exists('{course}', ['idnumber' => $idnumber]);
    }

    private function create_enrolment($course, $type = "manual") {
        $plugin = enrol_get_plugin($type);
        $plugin->add_instance($course, $plugin->get_instance_defaults());
    }

    private function enrol_user($course) {
        global $USER;
    }

}