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
/*
 That is the file which is responsible for communicating with solr
 brings the response and forwwward to renderer.
*/
require_once($CFG->dirroot . "/admin/tool/coursesearch/SolrPhpClient/Apache/Solr/Service.php");
require_once($CFG->dirroot . "/admin/tool/coursesearch/SolrPhpClient/Apache/Solr/HttpTransport/Curl.php");
require_once($CFG->dirroot . "/admin/tool/coursesearch/Solr.php");
class SearchResults
{
    /**
     * brings the reponse from solr
     *
     * @param string qry, int offset, int count, string fq(filter query), string sortby
     * Array $options Config array
     * @return Apache_solr_response object
     */
    public function tool_coursesearch_query($qry, $offset, $count, $fq, $sortby, $options) {
        global $CFG;
        $response = null;
        $options  = self::tool_coursesearch_solr_params();
        $solr     = new tool_coursesearch_solrlib();
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
            $params['group']                      = 'true';
            $params['group.field']                = 'courseid';
            $params['group.ngroups']              = 'true';
            $response                             = $solr->search($qry, $offset, $count, $params);
            if (!$response->getHttpStatus() == 200) {
                $response = null;
            }
        }
        return $response;
    }
    /**
     * takes the params offset and count from plugin & reponse to render
     *
     * @param array $array offset & count 
     * @return Apache_solr_response object
     */
    public function tool_coursesearch_search($array) {
        $config = self::tool_coursesearch_solr_params();
        $qry    = stripslashes(optional_param('search', '', PARAM_TEXT));
        $offset = isset($array['offset'])?$array['offset']:0;
        $count  = isset($array['limit'])?$array['limit']:20;   // TODO input from user how many results perpage.
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
                $response = $results->grouped->courseid;
                $header   = $results->responseHeader;
                echo 'Query Time ' . $header->QTime / 1000 . ' Seconds';
                if (isset($results->spellcheck->suggestions->collation)) {
                    $didyoumean = $results->spellcheck->suggestions->collation->collationQuery;
                } else {
                    $didyoumean = false;
                }
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
    /**
     * gives the count of results. we filter the hidden course by iterating through courses.
     *
     * @param object Apache_solr_response
     * @return int count
     */
    public function tool_coursesearch_coursecount($response) {
        $count = $response->ngroups;
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
                        $count -= 1;
                    }
                }
            }
        }
        return $count;
    }
    /**
     * Returns the error code either 0, 02 or 12.
     * 0 Everything is okay 
     * 1 Admin tool is not installed
     * 02 Ping to solr failed.
     * 12 Both issues 
     * @param void
     * @return int errorcode
     */
    public function is_dependency_resolved() {
        $errorcode = 0;
        global $CFG;
        $obj = new tool_coursesearch_solrlib();
        if (!array_key_exists('coursesearch', get_plugin_list('tool'))) {
            $errorcode = 1;
        }
        if (!$obj->connect($this->tool_coursesearch_solr_params(), true, $CFG->dirroot . '/admin/tool/coursesearch/')) {
            $errorcode .= 2;
        }
        return $errorcode;
    }
}