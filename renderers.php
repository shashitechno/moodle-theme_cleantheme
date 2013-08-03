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
class theme_cleantheme_core_renderer extends core_renderer
{
}
require_once($CFG->dirroot . "/course/renderer.php");
require_once($CFG->dirroot . "/admin/tool/coursesearch/SolrPhpClient/Apache/Solr/Service.php");
require_once($CFG->dirroot . "/admin/tool/coursesearch/SolrPhpClient/Apache/Solr/HttpTransport/Curl.php");
require_once($CFG->dirroot . "/admin/tool/coursesearch/Basic-solr-functions.class.inc.php");
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
            html_writer::link(new moodle_url('search.php?search=' . optional_param(
                'search', '', PARAM_TEXT) . '&sort=score&order=desc'), 'By Relevance'),
            html_writer::link(new moodle_url('search.php?search=' . optional_param(
                'search', '', PARAM_TEXT) . '&sort=shortname&order=desc'), 'By ShortName'),
            html_writer::link(new moodle_url('search.php?search=' . optional_param(
                'search', '', PARAM_TEXT) . '&sort=startdate&order=asc'), 'Oldest'),
            html_writer::link(new moodle_url('search.php?search=' . optional_param(
                'search', '', PARAM_TEXT) . '&sort=startdate&order=desc'), 'Newest')
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
            $displayoptions = array(
                'sort' => array(
                    'displayname' => 1
                )
            );
            $perpage        = optional_param('perpage', 0, PARAM_RAW);
            if ($perpage !== 'all') {
                $displayoptions['limit']  = ((int) $perpage <= 0) ? $CFG->coursesperpage : (int) $perpage;
                $page                     = optional_param('page', 0, PARAM_INT);
                $displayoptions['offset'] = $displayoptions['limit'] * $page;
            }
            $displayoptions['paginationurl']      = new moodle_url('/course/search.php', $searchcriteria);
            $displayoptions['paginationallowall'] = true;
            $response                             = self::tool_coursesearch_search($displayoptions);
            $courses                              = array();
            foreach ($response->docs as $doc) {
                $resultinfo = array();
                $docid      = strval($doc->courseid);
                $doc->id    = $doc->courseid;
                foreach ($doc as $key => $value) {
                    $resultinfo[$key] = $value;
                }
                $obj[$docid] = json_decode(json_encode($resultinfo), false);
                if (empty($obj[$docid]->visibility)) {
                    context_helper::preload_from_record($obj[$docid]);
                    if (!has_capability('moodle/course:viewhiddencourses', context_course::instance($docid))) {
                        unset($obj[$docid]);
                    }
                }
                if (isset($obj[$docid])) {
                    $courses[$docid] = new course_in_list($obj[$docid]);
                }
            }
            $class = 'course-search-result';
            foreach ($searchcriteria as $key => $value) {
                if (!empty($value)) {
                    $class .= ' course-search-result-' . $key;
                }
            }
            $chelper = new coursecat_helper();
            $chelper->set_show_courses(
                self::COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT)->set_courses_display_options($displayoptions)->set_search_criteria(
                $searchcriteria)->set_attributes(array(
                'class' => $class
            ));
            $totalcount  = $this->tool_coursesearch_coursecount($response);
            $courseslist = $this->coursecat_courses($chelper, $courses, $totalcount);
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
    /*
     * Search functions
     */
    public function tool_coursesearch_query($qry, $offset, $count, $fq, $sortby, $options) {
        global $CFG;
        $response = null;
        $options  = self::tool_coursesearch_solr_params();
        $solr     = new Solr_basic();
        if ($solr->connect($options, true, $CFG->dirroot . '/admin/tool/coursesearch/')) {
            $params            = array();
            $params['defType'] = 'dismax';
            $params['qf']      = 'idnumber^5 fullname^10 shortname^5 summary^3.5 startdate^1.5 content filename';
            if (empty($qry) || $qry == '*' || $qry == '*:*') {
                $params['q.alt'] = "*:*";
                $qry             = '';
            }
            $params['pf']                         = 'fullname^15 shortname^10';
            $params['fq']                         = $fq;
            $params['fl']                         = '*,score';
            $params['hl']                         = 'on';
            $params['hl.fl']                      = 'fullname';
            $params['hl.snippets']                = '3';
            $params['hl.fragsize']                = '50';
            $params['sort']                       = $sortby;
            $params['spellcheck.onlyMorePopular'] = 'false';
            $params['spellcheck.extendedResults'] = 'false';
            $params['spellcheck.collate']         = 'true';
            $params['spellcheck.count']           = '1';
            $params['spellcheck']                 = 'true';
            $response                             = $solr->search($qry, $offset, $count, $params);
            if (!$response->getHttpStatus() == 200) {
                $response = null;
            }
        }
        return $response;
    }
    public function tool_coursesearch_search($array) {
        $config = self::tool_coursesearch_solr_params();
        $qry    = stripslashes($_GET['search']);
        $offset = $array['offset'];
        $count  = $array['limit'];
        $fq     = (isset($_GET['fq'])) ? $_GET['fq'] : '';
        $sort   = (isset($_GET['sort'])) ? $_GET['sort'] : '';
        $order  = (isset($_GET['order'])) ? $_GET['order'] : '';
        $isdym  = (isset($_GET['isdym'])) ? $_GET['isdym'] : 0;
        $fqitms = '';
        $out    = array();
        if (!$qry) {
            $qry = '';
        }
        if ($sort && $order) {
            $sortby = $sort . ' ' . $order;
        } else {
            $sortby = '';
            $order  = '';
        }
        if ($qry) {
            $results = self::tool_coursesearch_query($qry, $offset, $count, $fqitms, $sortby, $config);
            if ($results) {
                $response = $results->response;
                $header   = $results->responseHeader;
                echo 'Query Time ' . $header->QTime / 1000 . ' Seconds';
                $teasers = get_object_vars($results->highlighting);
                if (isset($results->spellcheck->suggestions->collation)) {
                    $didyoumean = $results->spellcheck->suggestions->collation->collationQuery;
                } else {
                    $didyoumean = false;
                }
                $out['hits']  = sprintf(("%d"), $response->numFound);
                $out['qtime'] = false;
                $outputinfo   = true;
                if ($outputinfo) {
                    $out['qtime'] = sprintf(("%.3f"), $header->QTime / 1000);
                }
                if ($didyoumean != false) {
                    echo html_writer::tag('h3', 'Did You Mean ' . html_writer::link(
                        new moodle_url('search.php?search=' . rawurlencode($didyoumean)), $didyoumean) . '?');
                }
            }
            return $response;
        }
    }
    /**
     * Return the array of solr configuration
     * @return array of solr configuration values 
     */
    public function tool_coursesearch_solr_params() {
        $options              = array();
        $options['solr_host'] = get_config('tool_coursesearch', 'solrhost');
        $options['solr_port'] = get_config('tool_coursesearch', 'solrport');
        $options['solr_path'] = get_config('tool_coursesearch', 'solrpath');
        return $options;
    }
    public function tool_coursesearch_coursecount($response) {
        $count = $response->numFound;
        foreach ($response->docs as $doc) {
                $resultinfo = array();
                $docid      = strval($doc->courseid);
                $doc->id    = $doc->courseid;
                foreach ($doc as $key => $value) {
                    $resultinfo[$key] = $value;
                }
                $obj[$docid] = json_decode(json_encode($resultinfo), false);
                if (empty($obj[$docid]->visibility)) {
                    context_helper::preload_from_record($obj[$docid]);
                    if (!has_capability('moodle/course:viewhiddencourses', context_course::instance($docid))) {
                       $count-=1;
                    }
                }
             }
             return $count;
    }
}
