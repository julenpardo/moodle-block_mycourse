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
 *
 * @package    block_mycourse_recommendations
 * @copyright  2016 onwards Julen Pardo & Mondragon Unibertsitatea
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mycourse_recommendations;

require_once('abstract_associator.php');
require_once($CFG->dirroot . '/blocks/mycourse_recommendations/classes/matrix/abstract_matrix.php');

use block_mycourse_recommendations\abstract_associator;

class cosine_similarity_associator implements abstract_associator {

    private $matrix;

    public function __construct($matrixinstance) {
        $this->matrix = $matrixinstance;
    }

    /**
     * Given the data of the historic users and the current ones, creates a matrix of association coefficients, with the
     * current users as rows, and the historic user as columns.
     *
     * @see cosine_similarity($vector1, $vector2).
     * @param array $currentdata A 2D array 
     * @param array $historicdata A 2D array
     * @return array The association matrix.
     */
    public function create_associations_matrix($currentdata, $historicdata) {
        $currentusers = array_keys($currentdata);
        $historicusers = array_keys($historicdata);

        foreach ($currentusers as $currentuser) {
            $currentviewsvector = $currentdata[$currentuser];

            $similarities = null;
            foreach ($historicusers as $historicuser) {
                $historicviewsvector = $historicdata[$historicuser];

                $similarity = $this->cosine_similarity($currentviewsvector, $historicviewsvector);
                $similarity = round($similarity, 4);
                $similarities[$historicuser] = $similarity;
            }

            $matrix[$currentuser] = $similarities;
        }

        return $matrix;
    }

    /**
     * Calculates the cosine similarity of two vectors, which will be the log views of a current user,
     * and a historic user.
     * The formula is: cos_sim($v1, $v2) = $v1 · $v2 / ||$v1|| * ||$v2||.
     *
     * @see dot_product($vector1, $vector2).
     * @see vector_module($vector).
     * @param array $vector1 The log views of a user.
     * @param array $vector2 The log views of another user.
     * @return double The cosine similarity between the two vectors, a number between 0 and 1, being 1 the
     * highest similarity.
     */
    private function cosine_similarity($vector1, $vector2) {
        $numerator = $this->dot_product($vector1, $vector2);
        $denominator = $this->vector_module($vector1) * $this->vector_module($vector2);

        if (intval($denominator) === 0) {
            $result = 1;
        } else {
            $result = $numerator / $denominator;
        }

        return $result;
    }

    /**
     * Calculates the dot product (aka scalar product) of two vectors, which will be the log views of
     * a current user, and a historic user.
     *
     * @param array $vector1 The log views of a user.
     * @param array $vector2 The log views of another user.
     * @return double The dot product of the two vectors.
     */
    private function dot_product($vector1, $vector2) {
        $result = 0;
        $modules = array_keys($vector1);

        foreach ($modules as $module) {
            $result += $vector1[$module] * $vector2[$module];
        }

        return $result;
    }

    /**
     * Calculates the module of a vector, which will be the log views of a user for the given modules.
     *
     * @param array $vector The vector of log views.
     * @return double The module of the vector.
     */
    private function vector_module($vector) {
        $result = 0;
        $modules = array_keys($vector);

        foreach ($modules as $module) {
            $result += pow($vector[$module], 2);
        }

        $result = sqrt($result);

        return $result;
    }
}
