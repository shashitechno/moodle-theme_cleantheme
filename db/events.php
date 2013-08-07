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
 * Meta course enrolment plugin event handler definition.
 *
 * @package theme_cleantheme
 * @category event
 * @copyright 2013 Shashikant Vaishnav 
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* List of handlers */
$handlers = array (
    'course_created' => array (
        'handlerfile'      => '/admin/tool/coursesearch/locallib.php',
        'handlerfunction'  => 'tool_coursesearch_course_created',
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
    'course_updated' => array (
        'handlerfile'      => '/admin/tool/coursesearch/',
        'handlerfunction'  => 'tool_coursesearch_course_updated',
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
    'course_deleted' => array (
        'handlerfile'      => '/admin/tool/coursesearch/Solr.php',
        'handlerfunction'  => 'tool_coursesearch_course_deleted',
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
);