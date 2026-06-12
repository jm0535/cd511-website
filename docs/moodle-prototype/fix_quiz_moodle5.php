<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodlecourse/config.php');

$quiz = $DB->get_record('quiz', ['id' => 1]);
$course = $DB->get_record('course', ['id' => $quiz->course]);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
$quiz_context = context_module::instance($cm->id);

echo "Quiz context: {$quiz_context->id}\n";

// Get question category in quiz context (category id 2 is "Default for Module 3 Quiz")
$qcat = $DB->get_record('question_categories', ['contextid' => $quiz_context->id, 'parent' => 1]);
if (!$qcat) {
    echo "No quiz category found\n";
    exit(1);
}
echo "Quiz category: {$qcat->id}\n";

// Get admin user
$admin = get_admin();

// Get all questions in the quiz category
$questions = $DB->get_records('question', ['category' => $qcat->id]);
echo "Questions in quiz category: " . count($questions) . "\n";

// For each question, ensure there's a bank entry and version
foreach ($questions as $q) {
    // Check if bank entry exists
    $entry = $DB->get_record('question_bank_entries', ['questioncategoryid' => $qcat->id, 'ownerid' => $admin->id]);
    if (!$entry) {
        // Create bank entry for this question
        $entry = new stdClass();
        $entry->questioncategoryid = $qcat->id;
        $entry->idnumber = null;
        $entry->ownerid = $admin->id;
        $entry->nextversion = 2;
        $entry->id = $DB->insert_record('question_bank_entries', $entry);
        echo "Created bank entry {$entry->id} for question {$q->id}\n";
    }

    // Check if version exists
    $version = $DB->get_record('question_versions', ['questionbankentryid' => $entry->id, 'questionid' => $q->id]);
    if (!$version) {
        $version = new stdClass();
        $version->questionbankentryid = $entry->id;
        $version->version = 1;
        $version->questionid = $q->id;
        $version->status = 'ready';
        $version->id = $DB->insert_record('question_versions', $version);
        echo "  Created version {$version->id}\n";
    }

    // Create question reference linking quiz slot to bank entry
    $slot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'slot' => $q->id]); // slot num might not match
    // Actually let's find the right slot
    $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
}

// Now properly link each slot to a question
$slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
$questions_list = $DB->get_records('question', ['category' => $qcat->id], 'id ASC');

// Delete old references
$DB->delete_records('question_references', ['usingcontextid' => $quiz_context->id, 'component' => 'mod_quiz', 'questionarea' => 'slot']);

$slot_array = array_values($slots);
$question_array = array_values($questions_list);

for ($i = 0; $i < min(count($slot_array), count($question_array)); $i++) {
    $slot = $slot_array[$i];
    $q = $question_array[$i];

    // Get bank entry for this question
    $version = $DB->get_record('question_versions', ['questionid' => $q->id]);
    if (!$version) {
        echo "No version for question {$q->id}\n";
        continue;
    }

    $ref = new stdClass();
    $ref->usingcontextid = $quiz_context->id;
    $ref->component = 'mod_quiz';
    $ref->questionarea = 'slot';
    $ref->itemid = $slot->id;
    $ref->questionbankentryid = $version->questionbankentryid;
    $ref->version = null; // Use latest version
    $ref->id = $DB->insert_record('question_references', $ref);
    echo "Linked slot {$slot->slot} to question {$q->name}\n";
}

echo "=== DONE ===\n";
echo "Reload the quiz Questions page now.\n";
