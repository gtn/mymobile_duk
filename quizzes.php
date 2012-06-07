<?php

require_once("../../config.php");

// Check login
require_login();

$PAGE->set_url('/theme/mymobile/quizzes.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$PAGE->set_heading('Quizzes');

echo $OUTPUT->header();

$sql =  "SELECT quiz.id, c.id AS course_id, c.fullname AS course_name, quiz.name AS quiz_name FROM {quiz} AS quiz, {course} c"
	." WHERE quiz.course=c.id ORDER BY course_name";

$quizzes = $DB->get_records_sql($sql);
$printCount = 0;

if ($quizzes) {
	foreach ($quizzes as $quiz) {
		if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $quiz->course_id)) {
			print_error('invalidcoursemodule');
		}
		$context = get_context_instance(CONTEXT_MODULE, $cm->id);
		if (!has_capability('mod/quiz:view', $context) || !has_capability('mod/quiz:attempt', $context)) {
			continue;
		}
		
		echo '<a href="'.$CFG->wwwroot.'/mod/quiz/view.php?id='.$cm->id.'">'.$quiz->course_name.' - '.$quiz->quiz_name."</a><br />";
		$printCount++;
	}
}

if (!$printCount) {
	echo 'No quizzes';
}

echo $OUTPUT->footer();
