<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodlecourse/config.php');

$admin = get_admin();
$course = $DB->get_record('course', ['shortname' => 'CD511-Course']);
$course_context = context_course::instance($course->id);

// Fix forum announcement
$forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news']);
if ($forum) {
    $existing = $DB->count_records('forum_discussions', ['forum' => $forum->id]);
    if ($existing == 0) {
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->name = 'Welcome to CD511!';
        $discussion->firstpost = 0;
        $discussion->userid = $admin->id;
        $discussion->groupid = -1;
        $discussion->assessed = 0;
        $discussion->timemodified = time();
        $discussion->usermodified = $admin->id;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->pinned = 0;
        $discussion->id = $DB->insert_record('forum_discussions', $discussion);

        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = 0;
        $post->userid = $admin->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = 1;
        $post->subject = $discussion->name;
        $post->message = '<p>Welcome to Introduction to Student-Centred Teaching! This course will guide you through the fundamentals of LMS management and flipped classroom design. Please review the modules and complete all activities before the deadline.</p>';
        $post->messageformat = 1;
        $post->messagetrust = 0;
        $post->attachment = '';
        $post->totalscore = 0;
        $post->mailnow = 0;
        $post->id = $DB->insert_record('forum_posts', $post);
        
        $DB->set_field('forum_discussions', 'firstpost', $post->id, ['id' => $discussion->id]);
        echo "Posted forum announcement\n";
    } else {
        echo "Forum already has discussions\n";
    }
}

// Get quiz
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
if (!$quiz) {
    echo "No quiz found\n";
    exit(1);
}

// Get or create question category
$qcat = $DB->get_record('question_categories', ['contextid' => $course_context->id]);
if (!$qcat) {
    $qcat = new stdClass();
    $qcat->contextid = $course_context->id;
    $qcat->name = 'Top for CD511';
    $qcat->info = '';
    $qcat->sortorder = 999;
    $qcat->stamp = make_unique_id_code();
    $qcat->parent = 0;
    $qcat->id = $DB->insert_record('question_categories', $qcat);
}

// Helper to insert a question
function insert_question($DB, $admin, $qcat_id, $name, $text, $type, $answers_data = null, $correct_tf = null) {
    $q = new stdClass();
    $q->category = $qcat_id;
    $q->parent = 0;
    $q->name = $name;
    $q->questiontext = $text;
    $q->questiontextformat = 1;
    $q->generalfeedback = '';
    $q->generalfeedbackformat = 1;
    $q->defaultmark = 1;
    $q->penalty = 0.3333333;
    $q->qtype = $type;
    $q->length = 1;
    $q->stamp = make_unique_id_code();
    $q->version = make_unique_id_code();
    $q->hidden = 0;
    $q->timecreated = time();
    $q->timemodified = time();
    $q->createdby = $admin->id;
    $q->modifiedby = $admin->id;
    $q->id = $DB->insert_record('question', $q);

    if ($type === 'multichoice') {
        $mc = new stdClass();
        $mc->questionid = $q->id;
        $mc->layout = 0;
        $mc->single = 1;
        $mc->shuffleanswers = 1;
        $mc->correctfeedback = 'Correct!';
        $mc->correctfeedbackformat = 1;
        $mc->partiallycorrectfeedback = 'Partially correct.';
        $mc->partiallycorrectfeedbackformat = 1;
        $mc->incorrectfeedback = 'Incorrect.';
        $mc->incorrectfeedbackformat = 1;
        $mc->answernumbering = 'abc';
        $mc->shownumcorrect = 1;
        $mc->id = $DB->insert_record('qtype_multichoice_options', $mc);

        foreach ($answers_data as $idx => $a) {
            $ans = new stdClass();
            $ans->question = $q->id;
            $ans->answer = $a['text'];
            $ans->answerformat = 0;
            $ans->fraction = $a['fraction'];
            $ans->feedback = '';
            $ans->feedbackformat = 1;
            $ans->id = $DB->insert_record('question_answers', $ans);
        }
    } elseif ($type === 'truefalse') {
        $tf = new stdClass();
        $tf->questionid = $q->id;
        $tf->trueanswer = 0;
        $tf->falseanswer = 0;
        $tf->id = $DB->insert_record('question_truefalse', $tf);

        $true_ans = new stdClass();
        $true_ans->question = $q->id;
        $true_ans->answer = 'True';
        $true_ans->answerformat = 0;
        $true_ans->fraction = $correct_tf ? 1 : 0;
        $true_ans->feedback = '';
        $true_ans->feedbackformat = 1;
        $true_ans->id = $DB->insert_record('question_answers', $true_ans);

        $false_ans = new stdClass();
        $false_ans->question = $q->id;
        $false_ans->answer = 'False';
        $false_ans->answerformat = 0;
        $false_ans->fraction = $correct_tf ? 0 : 1;
        $false_ans->feedback = '';
        $false_ans->feedbackformat = 1;
        $false_ans->id = $DB->insert_record('question_answers', $false_ans);

        $DB->set_field('question_truefalse', 'trueanswer', $true_ans->id, ['id' => $tf->id]);
        $DB->set_field('question_truefalse', 'falseanswer', $false_ans->id, ['id' => $tf->id]);
    }
    return $q->id;
}

// Add questions
$questions = [
    ['name' => 'Q1: LMS Definition', 'text' => 'What does LMS stand for in educational technology?', 'type' => 'multichoice', 'answers' => [
        ['text' => 'Learning Management System', 'fraction' => 1],
        ['text' => 'Learning Media Software', 'fraction' => 0],
        ['text' => 'Lesson Management Service', 'fraction' => 0],
        ['text' => 'Local Media Storage', 'fraction' => 0],
    ]],
    ['name' => 'Q2: Flipped Classroom', 'text' => 'In a flipped classroom model, students typically review content at home and engage in activities during class time.', 'type' => 'truefalse', 'correct' => true],
    ['name' => 'Q3: Moodle Features', 'text' => 'Which of the following is NOT a standard Moodle activity module?', 'type' => 'multichoice', 'answers' => [
        ['text' => 'Quiz', 'fraction' => 0],
        ['text' => 'Forum', 'fraction' => 0],
        ['text' => 'Video Editor', 'fraction' => 1],
        ['text' => 'Assignment', 'fraction' => 0],
    ]],
    ['name' => 'Q4: Assessment Types', 'text' => 'Formative assessment is primarily used to:', 'type' => 'multichoice', 'answers' => [
        ['text' => 'Grade students at the end of a course', 'fraction' => 0],
        ['text' => 'Monitor student learning and provide ongoing feedback', 'fraction' => 1],
        ['text' => 'Replace final examinations', 'fraction' => 0],
        ['text' => 'Determine scholarship eligibility', 'fraction' => 0],
    ]],
    ['name' => 'Q5: Active Learning', 'text' => 'Active learning strategies require students to engage with course material through discussion, problem-solving, and reflection.', 'type' => 'truefalse', 'correct' => true],
    ['name' => 'Q6: Blended Learning', 'text' => 'Blended learning combines online digital media with traditional classroom methods.', 'type' => 'truefalse', 'correct' => true],
    ['name' => 'Q7: Student Engagement', 'text' => 'Which strategy is most effective for increasing student engagement in an online course?', 'type' => 'multichoice', 'answers' => [
        ['text' => 'Posting all content as PDF documents only', 'fraction' => 0],
        ['text' => 'Using interactive activities, discussions, and multimedia', 'fraction' => 1],
        ['text' => 'Removing all deadlines to reduce stress', 'fraction' => 0],
        ['text' => 'Limiting communication to email only', 'fraction' => 0],
    ]],
    ['name' => 'Q8: Feedback Importance', 'text' => 'Timely and specific feedback is essential for student improvement and motivation.', 'type' => 'truefalse', 'correct' => true],
    ['name' => 'Q9: Collaborative Learning', 'text' => 'Collaborative learning in an LMS can be facilitated through:', 'type' => 'multichoice', 'answers' => [
        ['text' => 'Individual essay assignments only', 'fraction' => 0],
        ['text' => 'Forums, wikis, and group projects', 'fraction' => 1],
        ['text' => 'Removing group features', 'fraction' => 0],
        ['text' => 'Disabling messaging', 'fraction' => 0],
    ]],
    ['name' => 'Q10: Course Design', 'text' => 'A well-designed online course should include clear learning objectives, varied activities, and multiple assessment opportunities.', 'type' => 'truefalse', 'correct' => true],
];

$slot_num = 1;
foreach ($questions as $qdata) {
    if ($qdata['type'] === 'multichoice') {
        $qid = insert_question($DB, $admin, $qcat->id, $qdata['name'], $qdata['text'], 'multichoice', $qdata['answers'], null);
    } else {
        $qid = insert_question($DB, $admin, $qcat->id, $qdata['name'], $qdata['text'], 'truefalse', null, $qdata['correct']);
    }

    $slot = new stdClass();
    $slot->quizid = $quiz->id;
    $slot->page = 1;
    $slot->requireprevious = 0;
    $slot->questionid = $qid;
    $slot->slot = $slot_num;
    $slot->maxmark = 1;
    $DB->insert_record('quiz_slots', $slot);
    echo "Added question {$slot_num}: {$qdata['name']}\n";
    $slot_num++;
}

echo "=== SETUP COMPLETE ===\n";
echo "Course URL: http://localhost/moodlecourse/course/view.php?id={$course->id}\n";
echo "Admin: admin / AdminPass123!\n";
echo "Students: student1-5 / StudentPass123!\n";
