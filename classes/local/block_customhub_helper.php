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

    /* TODO: Make the parent category editable config. */
    const PARENT_CATEGORY = 8;

    const CONTAINER_NEW_EMBED = 2;
    const CONTAINER_NEW_EMBED_WITHOUT_BLOCKS = 3;
    const CONTAINER_NEW_WINDOW = 4;
    const CONTAINER_NEW_EXISTING_WINDOW = 5;

    /**
     * Create the collaboration course with mod_external.
     * @param object $coursedata
     * @return object $course
     */
    public function create_collaboration_course($coursedata) {
        global $DB;
        $ccat = $this->create_collaboration_course_category();
        $course = $this->create_course($ccat, $coursedata);
        $enrolinstance = $this->create_enrolment($course, 'manual');

        // There's only the possibility "role -> student" to enrol users to this auto-course. 
        // Otherwise users were able to manipulate the course and see others gradings etc.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->enrol_user($studentrole, $enrolinstance);

        $this->create_lti_module($coursedata, $course);
        return $course;
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
                'parent' => self::PARENT_CATEGORY,
                // 'parent' => 0,
                'descriptionformat' => 0,
                'visible' => 1,
                'description' => '',
            ];
            $coursecat = $generator->create_category($record);
        }
        return $coursecat;
    }

    /**
     * Create cousre
     * @param object $ccat Record of course_categories table
     * @param object $coursedata Submitted course data.
     * @return object $course
     */
    public function create_course($ccat, $coursedata) {
        global $CFG, $DB;
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
        if (!$course = $DB->get_record('course', ['idnumber' => $coursedata->id])) {
            $course = $generator->create_course($courserecord, ['createsections' => true]);
        }

        return $course;
    }

    /**
     * Set the courseregid as id in $courselist
     * @param array $courselist
     * @return array $courses
     */
    public function set_courseregid_as_key($courslist) {
        $courses = [];
        foreach($courslist as $course){
            $courses[$course['id']] = (object)$course;
        }
        return $courses;
    }

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

    /**
     * Check if course exists.
     * @param array $conditions
     * @return bool
     */
    private function course_exists($conditions) { 
        global $DB;
        return $DB->record_exists('course', $conditions);
    }

    /**
     * Create Enrolment methode
     * @param object $course
     * @param string $type enrolment methode
     * @return object enrolment instance
     */
    private function create_enrolment($course, $type = "manual") {
        global $DB;
        $plugin = enrol_get_plugin($type);
        if (!$enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol'=>$type])) {
            $enrolinstanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
            $enrolinstance = $DB->get_record('enrol', ['id' => $enrolinstanceid]);
        }
        return $enrolinstance;
    }

    /**
     * Enrol a user to a course.
     * @param object $role
     * @param object $enrolinstance
     */
    private function enrol_user($role, $enrolinstance) {
        global $USER;
        $enrolplugin = enrol_get_plugin($enrolinstance->enrol);
        $enrolplugin->enrol_user($enrolinstance, $USER->id, $role->id);  
    }

    /**
     * Create lti module instance
     * @param object $coursedata
     * @param object $course
     */
    private function create_lti_module($coursedata, $course) {
        global $CFG;
        require_once($CFG->libdir . '/testing/generator/lib.php');
        require_once($CFG->libdir . '/testing/generator/data_generator.php');
        $generator = new \testing_data_generator();
        $modgenerator = $generator->get_plugin_generator('mod_lti');
        $coverage = json_decode($coursedata->coverage);
        $record = [
            'typename' => 'Test Einschreibung',
            'course' => $course->id,
            'toolurl' => $coursedata->courseurl,
            // 'password' => $coursedata->secret,
            'password' => $coverage->secret,
            'resourcekey' => 'teachSHARE',
            // 'instructorchoicesendname',
            // 'instructorchoicesendemailaddr',
            // 'instructorchoiceacceptgrades',
            // 'typeid',
            'ltiversion' => 'LTI-1p0',
            'launchcontainer' => self::CONTAINER_NEW_WINDOW
        ];

        // print_r($record);die;
        $modgenerator->create_instance($record);
    }
}