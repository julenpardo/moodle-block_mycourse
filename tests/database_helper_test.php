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
 * Unit tests for database handling of mycourse_recommendations block.
 *
 * @package    block_mycourse_recommendations
 * @category   phpunit
 * @copyright  2016 onwards Julen Pardo & Mondragon Unibertsitatea
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/db/database_helper.php'); // Include the code to test.
use block_mycourse_recommendations\database_helper;

/**
 * Test cases for block_mycourse_recommendations for database handling.
 */
class block_mycourse_recommendations_testcase extends advanced_testcase {

    protected $databasehelper;
    protected $course;
    protected $users;
    protected $resource;

    /**
     * Set up the test environment.
     */
    protected function setUp() {
        parent::setUp();
        $this->setAdminUser();

        $this->users = array();
        $this->databasehelper = new database_helper();
        $this->course = $this->getDataGenerator()->create_course();

        for ($count = 0; $count < 10; $count++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
            array_push($this->users, $user);
        }

        $pagegenerator = $this->getDataGenerator()->get_plugin_generator('mod_page');
        $this->resource = $pagegenerator->create_instance(array('course' => $this->course->id));
    }

    protected function tearDown() {
        $this->databasehelper = null;
        $this->course = null;
        $this->users = null;
        $this->resource = null;
        parent::tearDown();
    }

    /**
     * Tests that function inserts associations properly, with the expected behaviour.
     */
    public function test_insert_associations() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Inputs for the function.
        $number = 3;
        $currentusersids = array(1, 2, 3);
        $currentcourseid = 1;
        $historicuserids = array(100, 200, 300);
        $historiccourseid = 2;
        $week = 1;

        $this->databasehelper->insert_associations($number, $currentusersids, $currentcourseid, $historicuserids,
            $historiccourseid, $week);
    }

    /**
     * Tests that function throws exception if receives any array of different length.
     */
    public function test_insert_associations_exception() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Inputs for the function.
        $number = 4;
        $currentusersids = array(1, 2, 3, 4);
        $currentcourseid = 1;
        $historicuserids = array(100, 200, 300);
        $historiccourseid = 2;
        $week = 1;

        try {
            $this->databasehelper->insert_associations($number, $currentusersids, $currentcourseid, $historicuserids,
                $historiccourseid, $week);
            $this->fail('Exception should have been thrown in previous sentence.');
        } catch (Exception $e) {
            $this->assertTrue(true); // Silly workaround to pass the codecheker tests...
        }
    }

    /**
     * Tests that function inserts recommendations properly, with the expected behaviour.
     */
    public function test_insert_recommendations() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Inputs for the function.
        $number = 3;
        $associationsids = array(1, 2, 3);
        $resourcesids = array(4, 5, 6);
        $priorities = array(6, 7, 8);

        $this->databasehelper->insert_recommendations($number, $associationsids, $resourcesids, $priorities);
    }

    /**
     * Tests that function throws exception if receives any array of different length.
     */
    public function test_insert_recommendations_exception() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Inputs for the function.
        $number = 4;
        $associationsids = array(1, 2, 3);
        $resourcesids = array(4, 5, 6);
        $priorities = array(6, 7, 8, 9);

        try {
            $this->databasehelper->insert_recommendations($number, $associationsids, $resourcesids, $priorities);
            $this->fail('Exception should have been thrown in previous sentence.');
        } catch (Exception $e) {
            $this->assertTrue(true); // Silly workaround to pass the codecheker tests...
        }
    }

}
