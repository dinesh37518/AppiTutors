<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Database\Database;
use App\Support\Timezone;

$pdo = Database::getConnection();

// 1. Ensure editorial author exists in users
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'editorial@apptutors.co.uk'");
$stmt->execute();
$authorId = $stmt->fetchColumn();

if (!$authorId) {
    $now = Timezone::nowUtc();
    $insertUser = $pdo->prepare("
        INSERT INTO users (email, role, status, display_name, firebase_uid, created_at, updated_at)
        VALUES ('editorial@apptutors.co.uk', 'MANAGER', 'ACTIVE', 'AppTutors Editorial Team', 'system_editorial_manager', :created_at, :updated_at)
    ");
    $insertUser->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $authorId = (int) $pdo->lastInsertId();
}

// 2. Insert sample published blog articles
$articles = [
    [
        'title' => 'Mastering GCSE Maths Revision: Key Strategies for Higher Tier Success',
        'slug' => 'mastering-gcse-maths-revision',
        'excerpt' => 'Practical advice for AQA and Edexcel GCSE Mathematics, focusing on past papers, algebraic proof, and exam time management.',
        'body' => "GCSE Mathematics remains one of the most critical milestone qualifications for UK students. As students approach their Year 11 exams, transition from passive note review to targeted, exam-board-specific practice is essential.

1. Focus on Problem-Solving and Mark Scheme Language
Exam boards such as AQA (8300) and Pearson Edexcel (1MA1) increasingly test multi-step mathematical reasoning. In Higher Tier papers, grade 7–9 questions frequently combine algebraic manipulation with geometric proof. When working through past paper questions, read the examiner reports to understand where common pitfalls occur.

2. Master the Core Non-Calculator Skills
Paper 1 is always non-calculator. Confidence with fraction arithmetic, surd simplification, and index laws prevents avoidable arithmetic errors that cost students vital marks under timed conditions.

3. Establish a Weekly Past Paper Cadence
Rather than revising topics in isolation, complete timed practice papers every week. Mark your work strictly using the official mark schemes, noting which topic areas require reinforcement with your tutor.

Consistent, deliberate practice alongside focused 1-to-1 tutoring provides the fastest route to mathematical fluency and exam confidence.",
        'published_at' => '2026-09-15 10:00:00',
    ],
    [
        'title' => 'Transitioning to A-Level Chemistry: Essential Preparation for Year 12',
        'slug' => 'transitioning-to-a-level-chemistry',
        'excerpt' => 'Core concepts, mathematical skills, and laboratory technique required to thrive in Sixth Form Chemistry under OCR and AQA specifications.',
        'body' => "The transition from GCSE Triple Science to A-Level Chemistry represents one of the steepest academic learning curves in the UK secondary curriculum. Understanding the shift in depth and rigorous application is critical for early Sixth Form success.

1. Solidify Fundamental Mole Calculations
Stoichiometry, solution concentrations, and ideal gas calculations form the mathematical bedrock of physical chemistry. Spend time before term starts reviewing the relationships between mass, moles, volume, and molar mass.

2. Embrace Mechanistic Thinking in Organic Chemistry
GCSE requires memorization of homologous series and simple reactions. In contrast, A-Level demands mastery of electron movement: curly arrows represent the movement of electron pairs. Whether tackling electrophilic addition or nucleophilic substitution, focus on why electrons move rather than memorizing isolated equations.

3. Develop Precision in Scientific Definitions
Examiners are exacting with terminology. Key definitions—such as First Ionization Energy, Enthalpy of Formation, and Electronegativity—must be quoted with absolute precision, including state symbols where appropriate.

Working with an experienced A-Level tutor allows students to demystify complex physical and organic concepts, establishing a competitive foundation for university entrance.",
        'published_at' => '2026-09-22 14:30:00',
    ]
];

foreach ($articles as $art) {
    $check = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = :slug");
    $check->execute([':slug' => $art['slug']]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("
            INSERT INTO blog_posts (
                author_user_id, reviewed_by_user_id, title, slug, excerpt, body, 
                status, published_at, reviewed_at, created_at, updated_at
            ) VALUES (
                :author_id, :reviewer_id, :title, :slug, :excerpt, :body, 
                'PUBLISHED', :published_at, :reviewed_at, :created_at, :updated_at
            )
        ");
        $ins->execute([
            ':author_id' => $authorId,
            ':reviewer_id' => $authorId,
            ':title' => $art['title'],
            ':slug' => $art['slug'],
            ':excerpt' => $art['excerpt'],
            ':body' => $art['body'],
            ':published_at' => $art['published_at'],
            ':reviewed_at' => $art['published_at'],
            ':created_at' => $art['published_at'],
            ':updated_at' => $art['published_at'],
        ]);
        echo "Seeded article: {$art['title']}\n";
    } else {
        echo "Article already exists: {$art['slug']}\n";
    }
}

echo "Editorial seed complete.\n";
