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
 * Unit tests for simple recommendations of mycourse_recommendations block.
 *
 * @package    block_mycourse_recommendations
 * @category   phpunit
 * @copyright  2016 onwards Julen Pardo & Mondragon Unibertsitatea
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/recommendator/simple_recommendator.php');
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/associator/cosine_similarity_associator.php');
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/matrix/decimal_matrix.php');
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/course_filter/course_filter.php');
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/db/database_helper.php');

use block_mycourse_recommendations\simple_recommendator;
use block_mycourse_recommendations\cosine_similarity_associator;
use block_mycourse_recommendations\decimal_matrix;
use block_mycourse_recommendations\course_filter;
use block_mycourse_recommendations\database_helper;

class block_mycourse_recommendations_simple_recommendator_testcase extends advanced_testcase {

    protected $recommendator;
    protected $previousyear;
    protected $previousstartdate;
    protected $previouscourseattributes;
    protected $previouscourses;

    protected $currentyear;
    protected $currentstartdate;
    protected $currentcourseattributes;
    protected $currentcourse;
    protected $dbhelper;

    public function setUp() {
        parent::setUp();
        $this->recommendator = new simple_recommendator(new cosine_similarity_associator(new decimal_matrix));

        $this->previousyear = 2015;
        $this->previousstartdate = strtotime("06-01-$this->previousyear");
        $this->previouscourseattributes = array('fullname' => 'Software Engineering II',
                                                'startdate' => $this->previousstartdate);

        $this->currentyear = 2016;
        $this->currentstartdate = strtotime("04-01-$this->currentyear");
        $this->currentcourseattributes = array('fullname' => 'Software Engineering II',
                                               'startdate' => $this->currentstartdate);

        $this->currentcourse = $this->create_courses($this->currentcourseattributes, 1);
        $this->dbhelper = new database_helper();
    }

    public function tearDown() {
        $this->recommendator = null;
        parent::tearDown();
    }

    /**
     * Creates a number of courses with the given attributes.
     *
     * @param array $attibutes The attributes of the course (fullname, startdate, etc.).
     * @param int $number The number of courses to create for the given previous parameters.
     * @return array The created courses.
     */
    protected function create_courses($attributes, $number) {
        $courses = array();

        for ($index = 0; $index < $number; $index++) {
            $courses[$index] = $this->getDataGenerator()->create_course($attributes);
        }

        return $courses;
    }

    /**
     * Creates resources.
     *
     * @param array $resources number of resources of a type for a course.
     */
    protected function create_resources($resources) {
        $createdresources = array();

        foreach ($resources as $courseid => $course) {
            foreach ($course as $resourcetype => $number) {
                $generator = $this->getDataGenerator()->get_plugin_generator($resourcetype);

                for ($index = 0; $index < $number; $index++) {
                    $resource = $generator->create_instance(array('course' => $courseid));
                    array_push($createdresources, $resource);
                }
            }
        }

        return $createdresources;
    }

    /**
     * Creates n number of students (roleid = 5) for the given course.
     *
     * @param int $courseid The course to enrol the student in.
     * @param int $number The number of students to create.
     */
    protected function create_and_enrol_students($courseid, $number) {
        $users = array();

        for ($index = 0; $index < $number; $index++) {
            $newuser = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($newuser->id, $courseid, 5); // The student role id.
            array_push($users, $newuser);
        }

        return $users;
    }

    protected function create_logview($userid, $courseid, $resourceid, $eventname, $component, $timestamp, $number) {
        global $DB;

        for ($index = 0; $index < $number; $index++) {
            $logview = new stdClass();

            $page = $DB->get_record('page', array('id' => $resourceid));
            $cm = get_coursemodule_from_instance('page', $page->id, $page->course, false, MUST_EXIST);
            $context = context_module::instance($cm->id);

            $logview->eventname = $eventname;
            $logview->component = $component;
            $logview->action = 'viewed';
            $logview->target = 'course_module';
            $logview->contextlevel = 50;
            $logview->userid = $userid;
            $logview->courseid = $courseid;
            $logview->edulevel = 2;
            $logview->contextid = $context->id;
            $logview->contextinstanceid = $context->instanceid;
            $logview->timecreated = $timestamp;

            $DB->insert_record('logstore_standard_log', $logview);
        }

    }

    public function test_create_associations() {
        global $DB;
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $eventname = '\\mod_page\\event\\course_module_viewed';
        $component = 'mod_page';

        // We have to creates a course before creating resources, with the attributes defined in setUp.
        $previouscourses = $this->create_courses($this->previouscourseattributes, 1);

        // We create the previous users...
        $previoususers = $this->create_and_enrol_students($previouscourses[0]->id, 3);

        // We create the resources...
        $numberofresources = 3;
        $previousresources = array();
        $previousresources[$previouscourses[0]->id]['mod_page'] = $numberofresources;
        $resources = $this->create_resources($previousresources);

        // We create the log views for the previous users...
        $previouslogviews = array();
        $previouslogviews[$previoususers[0]->id] = array(10, 3, 5);
        $previouslogviews[$previoususers[1]->id] = array(4, 1, 2);
        $previouslogviews[$previoususers[2]->id] = array(0, 7, 7);

        foreach ($previouslogviews as $userid => $resourceslogviews) {
            foreach ($resourceslogviews as $resourceindex => $logviews) {
                $this->create_logview($userid, $previouscourses[0]->id, $resources[$resourceindex]->id,
                                      $eventname, $component, $this->previousstartdate, $logviews);
            }
        }

        // We create the current users...
        $currentcourses = $this->create_courses($this->currentcourseattributes, 1);

        $currentusers = $this->create_and_enrol_students($currentcourses[0]->id, 2);

        // We create the resources...
        $numberofresources = 3;
        $currentresources = array();
        $currentresources[$currentcourses[0]->id]['mod_page'] = $numberofresources;
        $resources = array();
        $resources = $this->create_resources($currentresources);

        // We create the log views for the current users...
        $currentlogviews = array();
        $currentlogviews[$currentusers[0]->id] = array(3, 4, 6);
        $currentlogviews[$currentusers[1]->id] = array(7, 3, 2);

        foreach ($currentlogviews as $userid => $resourceslogviews) {
            foreach ($resourceslogviews as $resourceindex => $logviews) {
                $this->create_logview($userid, $currentcourses[0]->id, $resources[$resourceindex]->id,
                                      $eventname, $component, $this->currentstartdate, $logviews);
            }
        }

        // After the logs are created, we can call the function we're testing.
        $this->recommendator->create_associations($currentcourses[0]->id, 2);

        // The number of rows of the table has to be equal to the number of current students, otherwise, something is wrong.
        $rowcount = $DB->count_records('block_mycourse_assoc');
        $this->assertEquals(count($currentusers), $rowcount);

        // We query the actual values, and we store them in an index-based array beginning from 0, not using the ids as keys.
        $actuals = array();
        $records = $DB->get_records('block_mycourse_assoc');
        $actuals = array_values($records);

        // We remove the "id" field of each record, because to make the assertion later is unnecessary.
        foreach ($actuals as $id => $actual) {
            unset($actual->id);
            $actuals[$id] = $actual;
        }

        // We create the expected values, determining the associations between users with the similarity coefficientes calculated
        // externaly.
        $expecteds = array();
        $expecteds[0] = new stdClass();
        $expecteds[0]->current_userid = $currentusers[0]->id;
        $expecteds[0]->current_courseid = $currentcourses[0]->id;
        $expecteds[0]->historic_userid = $previoususers[2]->id;
        $expecteds[0]->historic_courseid = $previouscourses[0]->id;
        $expecteds[0]->week = 2;

        $expecteds[1] = new stdClass();
        $expecteds[1]->current_userid = $currentusers[1]->id;
        $expecteds[1]->current_courseid = $currentcourses[0]->id;
        $expecteds[1]->historic_userid = $previoususers[0]->id;
        $expecteds[1]->historic_courseid = $previouscourses[0]->id;
        $expecteds[1]->week = "2";

        // Probably asserting each object, instead of the whole arrays of objects, will cause less trouble.
        foreach (array_keys($actuals) as $index) {
            $this->assertEquals($expecteds[$index], $actuals[$index]);
        }
    }
}
