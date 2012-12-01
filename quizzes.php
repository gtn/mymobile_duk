<?php

require_once("../../config.php");

// Check login
require_login();

$PAGE->set_url('/theme/quoodle/quizzes.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$PAGE->set_heading('Quizzes');


// all courses with quizzes
$coursesWithQuizzes = $DB->get_records_sql("SELECT c.id, c.id AS tmp, c.fullname, c.visible FROM {quiz} AS quiz, {course} c".
	" WHERE quiz.course=c.id GROUP BY c.id");
// TODO: check if user enrolled in those courses?


// get all quizzes
$quizzes = get_all_instances_in_courses('quiz', $coursesWithQuizzes);


// recheck quizzes, if user can access them
$printQuizzes = array();
if ($quizzes) {
	foreach ($quizzes as $quiz) {
		if (!$coursesWithQuizzes[$quiz->course]->visible) {
			// hidden course
			continue;
		}

		$context = get_context_instance(CONTEXT_MODULE, $quiz->coursemodule);
		if (!has_capability('mod/quiz:view', $context) || !has_capability('mod/quiz:attempt', $context)) {
			continue;
		}

		if (!quoodle_can_attempt_quiz($quiz->coursemodule)) {
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
	
	echo '</ul>';
}

echo $OUTPUT->footer();
















function quoodle_can_attempt_quiz($id) {
	// test can attempt
	global $DB, $CFG;
	require_once($CFG->libdir.'/gradelib.php');
	require_once($CFG->dirroot.'/mod/quiz/locallib.php');
	require_once($CFG->libdir . '/completionlib.php');
	
	// the code below is an almost copy of the mod/quiz/view.php code!!!
	// i just disabled some parts which are not needed here -- Daniel

//if ($id) {
    if (!$cm = get_coursemodule_from_id('quiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
/* } else {
    if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
        print_error('invalidquizid', 'quiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
} */

// Check login and get context.
//require_login($course, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/quiz:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/quiz:attempt', $context);
$canreviewmine = has_capability('mod/quiz:reviewmyattempts', $context);
$canpreview = has_capability('mod/quiz:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$quizobj = quiz::create($cm->instance, $USER->id);
$accessmanager = new quiz_access_manager($quizobj, $timenow,
        has_capability('mod/quiz:ignoretimelimits', $context, null, false));
$quiz = $quizobj->get_quiz();

// Log this request.
//add_to_log($course->id, 'quiz', 'view', 'view.php?id=' . $cm->id, $quiz->id, $cm->id);

$completion = new completion_info($course);
//$completion->set_module_viewed($cm);

// Initialize $PAGE, compute blocks.
//$PAGE->set_url('/mod/quiz/view.php', array('id' => $cm->id));

// Create view object which collects all the information the renderer will need.
$viewobj = new mod_quiz_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine;

// Get this user's attempts.
$attempts = quiz_get_user_attempts($quiz->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
if ($unfinishedattempt = quiz_get_user_attempt_unfinished($quiz->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $quizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == quiz_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == quiz_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new quiz_attempt($attempt, $quiz, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = quiz_get_best_grade($quiz, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the quiz don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = quiz_rescale_grade($lastfinishedattempt->sumgrades, $quiz, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

//$title = $course->shortname . ': ' . format_string($quiz->name);
//$PAGE->set_title($title);
//$PAGE->set_heading($course->fullname);
//$output = $PAGE->get_renderer('mod_quiz');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts, $context);

    $viewobj->attemptcolumn  = $quiz->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
            quiz_has_grades($quiz);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($quiz->grade != $quiz->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = quiz_has_feedback($quiz) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
        !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/quiz:manage', $context);
$viewobj->editurl = new moodle_url('/mod/quiz/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $quizobj->start_attempt_url();
$viewobj->startattemptwarning = $quizobj->confirm_start_attempt_message($unfinished);
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this quiz.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($quiz->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'quiz',
            quiz_get_grading_option_name($quiz->grademethod));
}

// Determine wheter a start attempt button should be displayed.
$viewobj->quizhasquestions = (bool) quiz_clean_layout($quiz->questions, true);
$viewobj->preventmessages = array();
if (!$viewobj->quizhasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptquiz', 'quiz');
        } else if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'quiz');
        }

    } else {
        if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                    $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptquiznow', 'quiz');
            } else {
                $viewobj->buttontext = get_string('reattemptquiz', 'quiz');
            }

        } else if ($canpreview) {
            $viewobj->buttontext = get_string('previewquiznow', 'quiz');
        }
    }

    // If, so far, we think a button should be printed, so check if they will be
    // allowed to access it.
    if ($viewobj->buttontext) {
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt
                && $viewobj->preventmessages = $viewobj->accessmanager->prevent_access()) {
            $viewobj->buttontext = '';
        }
    }
}

if (!($canattempt || $canpreview
          || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    return false;
} else {
	return (bool) $viewobj->buttontext;
}

}