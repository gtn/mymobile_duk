<?php

require_once("../../config.php");

// Check login
require_login();

$PAGE->set_url('/theme/mymobile/quizzes.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$PAGE->set_heading('Quizzes');


// all courses with quizzes
$coursesWithQuizzes = $DB->get_records_sql("SELECT c.id, c.id AS tmp, c.fullname FROM {quiz} AS quiz, {course} c".
	" WHERE quiz.course=c.id GROUP BY c.id");
// TODO: check if user enrolled in those courses?


// get all quizzes
$quizzes = get_all_instances_in_courses('quiz', $coursesWithQuizzes);


// recheck quizzes, if user can access them
$printQuizzes = array();
if ($quizzes) {
	foreach ($quizzes as $quiz) {
		$context = get_context_instance(CONTEXT_MODULE, $quiz->coursemodule);
		if (!has_capability('mod/quiz:view', $context) || !has_capability('mod/quiz:attempt', $context)) {
			continue;
		}
		
		$printQuizzes[] = $quiz;
	}
}


echo $OUTPUT->header();

if (!$printQuizzes) {
	echo 'No quizzes';
} else {
	$lastCourseId = null;
	foreach ($printQuizzes as $quiz) {
	
		// grouping by course
		if ($lastCourseId != $quiz->course) {
			if ($lastCourseId) echo '</ul>';

			echo '<div class="headingwrap ui-bar-b ui-footer" style="margin: 20px 0 10px 0"><h2 class="main ui-title">'.$coursesWithQuizzes[$quiz->course]->fullname.'</h2></div>';
			echo '<ul data-role="controlgroup">';
			$lastCourseId = $quiz->course;
		}
		
		echo '<li><div class="coursebox clearfix"><div class="info">';
		echo '<a href="'.$CFG->wwwroot.'/mod/quiz/view.php?id='.$quiz->coursemodule.'" data-role="button" data-icon="arrow-r" data-iconpos="right" data-theme="d" style="text-align: left;">';
		echo $quiz->name;
		echo '</a></div></div></li>';
	}
}

echo $OUTPUT->footer();
