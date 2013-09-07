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
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_cleantheme
 * @copyright  2013
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_cleantheme_core_renderer extends theme_bootstrapbase_core_renderer
{
}
require_once($CFG->dirroot . "/course/renderer.php");
class theme_cleantheme_core_course_renderer extends core_course_renderer
{
    /**   @override
     * Renders html to display a course search form
     *
     * @param string $value default value to populate the search field
     * @param string $format display format - 'plain' (default), 'short' or 'navbar'
     * @return string
     */
    public function course_search_form($value = '', $format = 'plain') {
        $this->page->requires->js_init_call('M.tool_coursesearch.auto');
        static $count = 0;
        $formid = 'coursesearch';
        if ((++$count) > 1) {
            $formid .= $count;
        }
        switch ($format) {
            case 'navbar':
                $formid    = 'coursesearchnavbar';
                $inputid   = 'navsearchbox';
                $inputsize = 40;
                break;
            case 'short':
                $inputid   = 'shortsearchbox';
                $inputsize = 12;
                break;
            default:
                $inputid   = 'coursesearchbox';
                $inputsize = 30;
        }
        $strsearchcourses = get_string("searchcourses");
        $searchurl        = new moodle_url('/course/search.php');
        $output           = html_writer::start_tag('form', array(
            'id' => $formid,
            'action' => $searchurl,
            'method' => 'get'
        ));
        $output .= html_writer::start_tag('fieldset', array(
            'class' => 'coursesearchbox invisiblefieldset'
        ));
        $output .= html_writer::tag('label', $strsearchcourses . ': ', array(
            'for' => $inputid
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'text',
            'id' => $inputid,
            'size' => $inputsize,
            'name' => 'search',
            'value' => s($value)
        ));
        $output .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('go')
        ));
        $items = array(
            html_writer::link(new moodle_url('/course/search.php', array(
                'search' => optional_param('search', '', PARAM_TEXT),
                'sort' => 'score',
                'order' => 'desc'
            )), 'By Relevance'),
            html_writer::link(new moodle_url('/course/search.php', array(
                'search' => optional_param('search', '', PARAM_TEXT),
                'sort' => 'shortname',
                'order' => 'desc'
            )), 'By ShortName'),
            html_writer::link(new moodle_url('/course/search.php', array(
                'search' => optional_param('search', '', PARAM_TEXT),
                'sort' => 'startdate',
                'order' => 'asc'
            )), 'Oldest'),
            html_writer::link(new moodle_url('/course/search.php', array(
                'search' => optional_param('search', '', PARAM_TEXT),
                'sort' => 'startdate',
                'order' => 'desc'
            )), 'Newest')
        );
        $output .= html_writer::alist($items, array(
            "class" => "solr_sort2"
        ), 'ol');
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');
        return $output;
    }
    /**
     * Renders html to display search result page
     *
     * @param array $searchcriteria may contain elements: search, blocklist, modulelist, tagid
     * @return string
     */
    public function search_courses($searchcriteria) {
        global $CFG;
        $content = '';
        if (!empty($searchcriteria)) {
            require_once($CFG->libdir . '/coursecatlib.php');
            require_once("$CFG->dirroot/$CFG->admin/tool/coursesearch/locallib.php");
            $displayoptions = array(
                'sort' => array(
                    'displayname' => 1
                )
            );
            $ob             = new tool_coursesearch_locallib();
            $perpage        = optional_param('perpage', 0, PARAM_RAW);
            if ($perpage !== 'all') {
                $displayoptions['limit']  = ((int) $perpage <= 0) ? $CFG->coursesperpage : (int) $perpage;
                $page                     = optional_param('page', 0, PARAM_INT);
                $displayoptions['offset'] = $displayoptions['limit'] * $page;
            }
            $displayoptions['paginationurl']      = new moodle_url('/course/search.php', $searchcriteria);
            $displayoptions['paginationallowall'] = true;
            $courses                              = array();
            $class                                = 'course-search-result';
            $chelper                              = new coursecat_helper();
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT)->set_courses_display_options(
            $displayoptions)->set_search_criteria($searchcriteria)->set_attributes(array(
                'class' => $class
            ));
            if ($ob->tool_coursesearch_pluginchecks() == '0') {
                $response   = $ob->tool_coursesearch_search($displayoptions);
                $resultinfo = array();
                foreach ($response->groups as $doclists => $doclist) {
                    foreach ($doclist->doclist->docs as $doc) {
                        $doc->id = $doc->courseid;
                        foreach ($doc as $key => $value) {
                            $resultinfo[$key] = $value;
                        }
                        $obj[$doc->courseid] = json_decode(json_encode($resultinfo), false);
                        if (($obj[$doc->courseid]->visibility) == '0') {
                            context_helper::preload_from_record($obj[$doc->courseid]);
                            if (!has_capability('moodle/course:viewhiddencourses', context_course::instance($doc->courseid))) {
                                unset($obj[$doc->courseid]);
                            }
                        }
                        if (isset($obj[$doc->courseid])) {
                            $courses[$doc->courseid] = new course_in_list($obj[$doc->courseid]);
                        }
                    }
                }
                $totalcount = $ob->tool_coursesearch_coursecount($response);
            } else {
                $courses    = coursecat::search_courses($searchcriteria, $chelper->get_courses_display_options());
                $totalcount = coursecat::search_courses_count($searchcriteria);
            }
            foreach ($searchcriteria as $key => $value) {
                if (!empty($value)) {
                    $class .= ' course-search-result-' . $key;
                }
            }
            $courseslist = $this->coursecat_courses($chelper, $courses, $totalcount);
            if (!get_config('tool_coursesearch', 'solrerrormessage')) {
                global $OUTPUT;
                switch ($ob->tool_coursesearch_pluginchecks()) {
                    case 1:
                        $content .= $OUTPUT->notification(get_string('admintoolerror', 'tool_coursesearch'), 'notifyproblem');
                        break;
                    case 02:
                        $content .= $OUTPUT->notification(get_string('solrpingerror', 'tool_coursesearch'), 'notifyproblem');
                        break;
                    case 12:
                        $content .= $OUTPUT->notification(get_string('dependencyerror', 'tool_coursesearch'), 'notifyproblem');
                }
            }
            if (!$totalcount) {
                if (!empty($searchcriteria['search'])) {
                    $content .= $this->heading(get_string('nocoursesfound', '', $searchcriteria['search']));
                } else {
                    $content .= $this->heading(get_string('novalidcourses'));
                }
            } else {
                $content .= $this->heading(get_string('searchresults') . ": $totalcount");
                $content .= $courseslist;
            }
            if (!empty($searchcriteria['search'])) {
                $content .= $this->box_start('generalbox mdl-align');
                $content .= $this->course_search_form($searchcriteria['search']);
                $content .= $this->box_end();
            }
        } else {
            $content .= $this->box_start('generalbox mdl-align');
            $content .= $this->course_search_form();
            $content .= html_writer::tag('div', get_string("searchhelp"), array(
                'class' => 'searchhelp'
            ));
            $content .= $this->box_end();
        }
        return $content;
    }
}
