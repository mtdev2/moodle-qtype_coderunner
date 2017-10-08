<?php
// This file is part of CodeRunner - http://coderunner.org.nz
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script generates a csv or excel download of all attempt-step data
 * (or at least all that's likely to be useful) on a specific quiz, passed
 * as URL parameter quizid. The 'format' (csv or excel) is a required parameter
 * too.
 * The user must have grade:viewall permissions to run the script.
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__.'/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir.'/tablelib.php');

// Get the quiz-id and format parameters from the URL.
$quizid = required_param('quizid', PARAM_INT);
$format = required_param('format', PARAM_RAW);  // csv or excel

// Login and check permissions.
$context = context_system::instance();
require_login();

// I'm not sure if the next three lines are ever relevant but ... what's to lose?
$PAGE->set_url('/question/type/coderunner/getallattempts.php');
$PAGE->set_context($context);
$PAGE->set_title('Get all quiz attempts');  // TODO: use get_string

$coursecontext = context_course::instance($COURSE->id);
if (!has_capability('moodle/grade:viewall', $coursecontext)) {
    //echo html::tag('p', get_string('unauthoriseddbaccess', 'qtype_coderunner'));
    echo html::tag('p', 'UNAUTHORISED TO USE THIS SCRIPT'); // TODO use get_string
} else {
    $table = new table_sql(uniqid());
    $fields = "concat(quiza.uniqueid, qasd.attemptstepid, qasd.id) as uniquekey,
        quiza.uniqueid as attemptid,
        u.firstname,
        u.lastname,
        u.email,
        qatt.slot,
        qatt.questionid,
        quest.name as qname,
        qattsteps.timecreated as timestamp,
        FROM_UNIXTIME(qattsteps.timecreated,'%Y/%m/%d %H:%i:%s') as datetime,
        qattsteps.fraction,
        qattsteps.state,
        qasd.attemptstepid,
        qasd.name as qasdname,
        qasd.value as value";

    $from = "{user} u
    JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
    JOIN {question_attempts} qatt ON qatt.questionusageid = quiza.uniqueid
    JOIN {question_attempt_steps} qattsteps ON qattsteps.questionattemptid = qatt.id
    JOIN {question_attempt_step_data} qasd on qasd.attemptstepid = qattsteps.id
    JOIN {question} quest ON quest.id = qatt.questionid";

    $where = "quiza.preview = 0
    AND qasd.name NOT RLIKE '-_.*'
    AND qasd.name NOT RLIKE '_.*'
    ORDER BY attemptid, slot, attemptstepid";

    $params = array('quizid' => $quizid);
    $table->define_baseurl($PAGE->url);
    $table->set_sql($fields, $from, $where, $params);
    $table->is_downloading($format, "allattemptson$quizid", "All quiz attempts $quizid");
    $pagesize = 100; // Surely this is irrelevant for a download?
    raise_memory_limit(MEMORY_EXTRA);
    $table->out($pagesize, false); // And out it goes
}

