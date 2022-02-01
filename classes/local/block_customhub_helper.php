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

        $this->check_cartridgexml($coursedata);
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

    private function check_cartridgexml($coursedata) {

        $curlurl = $coursedata->courseurl;

        //send an identification token if the site is registered on the hub
        $registrationmanager = new \tool_customhub\registration_manager();
        $registeredhub = $registrationmanager->get_registeredhub($coursedata->huburl, true);
        if (!empty($registeredhub)) {
            $token = $registeredhub->token;
            $curlurl .= '&token=' . $token;
        }

        $ch = curl_init($curlurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);

        if (empty($data)) {
            throw new \moodle_exception('Cartrige URL is not readable. Is the LTI Authentication active?');
        }

        if(strpos($data, 'pluginnotenabled')) {
            throw new \moodle_exception('The LTI Authentication method is not active. Please contact the administrator.');
        }

        if (strpos($data, 'enrolisdisabled')) {
            throw new \moodle_exception('The LTI Enrolment method is not active. Please contact the administrator.');
        }

        curl_close($ch);
    }

    /**
     * Start an asynchronous restore of the given backup file to the given targetcategory.
     *
     * @param \stored_file $file file to restore
     * @param int $targetcat id of the target category the course backup file should be restored to
     * @throws \backup_helper_exception if backupfile is damaged/corrupt
     * @throws \moodle_exception if input parameters aren't valid or general error occurs
     * @throws \restore_controller_exception if restore process fails
     */
    public static function start_async_restore(\stored_file $file, int $targetcat = 0) {
        global $CFG, $USER, $DB;
        if (!$file) {
            throw new \moodle_exception('No file object given. Restore process aborted.');
        }

        if (!$targetcat || $targetcat == 0) {
            throw new \moodle_exception('No valid target category for restore process given. Aborting.');
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // Extraction mostly copied from \backup_general_helper::get_backup_information_from_mbz().
        $tmpname = 'mbsnewcourserestore_' . pathinfo($file->get_filename())['basename'] . '_' . time();
        $tmpdir = $CFG->tempdir . '/backup/' . $tmpname;
        $fp = get_file_packer('application/vnd.moodle.backup');
        $extracted = $fp->extract_to_pathname($file, $tmpdir);
        @unlink($file);
        $moodlefile = $tmpdir . '/' . 'moodle_backup.xml';
        if (!$extracted || !is_readable($moodlefile)) {
            throw new \backup_helper_exception('missing_moodle_backup_xml_file', $moodlefile);
        }

        $info = \backup_general_helper::get_backup_information($tmpname);
        list($fullname, $shortname) =
            \restore_dbops::calculate_course_names(0, $info->original_course_fullname, $info->original_course_shortname);
        $cdata = (object) [
            'category' => $targetcat,
            'shortname' => $shortname,
            'fullname' => $info->original_course_fullname . get_string('restoring', 'block_mbsnewcourse'),
            'visible' => 1,
            'newsitems' => 0, // Prevent creation of a new forum when course_created event is fired.
        ];
        $course = create_course($cdata);
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'), '*', MUST_EXIST);
        // ...add enrol instances.
        if (!$DB->record_exists('enrol', array('courseid' => $course->id, 'enrol' => 'manual'))) {
            if ($manual = enrol_get_plugin('manual')) {
                $manual->add_default_instance($course);
            }
        }
        enrol_try_internal_enrol($course->id, $USER->id, $teacherrole->id);
        $coursecontext = \context_course::instance($course->id);

        if (!has_capability('moodle/restore:restorecourse', $coursecontext)) {
            throw new \moodle_exception('nopermissions');
        }

        $rc = new \restore_controller(
            $tmpname,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_ASYNC,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        if (!$rc->execute_precheck()) {
            if (is_array($rc->get_precheck_results()) && !empty($rc->get_precheck_results()['errors'])) {
                delete_course($course->id);
                throw new \moodle_exception('cannotrestore', '', '', null, $rc->get_precheck_results()['errors']);
            }
        }
        $restoreid = $rc->get_restoreid();

        $asynctask = new \core\task\asynchronous_restore_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data(['backupid' => $restoreid]);
        \core\task\manager::queue_adhoc_task($asynctask);
    }

    /**
     * Create a Demo Course Category if necessary.
     * @return $object course_category record
     */
    public static function get_users_course_category() {
        global $DB, $USER;
        if (!empty($USER->mbs_schoolcat->id)) {
            return $DB->get_record('course_categories', ['id' => $USER->mbs_schoolcat->id]);
        }
        return $DB->get_record('course_categories', ['name' => 'Miscellaneous']);
    }
}