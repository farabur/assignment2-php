<?php
// Only One Word - Puzzle Generator
$errors = [];
$puzzles = [];

// Default values for the form
$gridSize = 10;
$numPuzzles = 2;
$wordInput = '';

// The 8 possible directions: [rowChange, colChange]
$directions = [
    [-1,  0], // 1. Up
    [ 1,  0], // 2. Down
    [ 0, -1], // 3. Left
    [ 0,  1], // 4. Right
    [-1, -1], // 5. Up-Left
    [-1,  1], // 6. Up-Right
    [ 1, -1], // 7. Down-Left
    [ 1,  1]  // 8. Down-Right
];

// Function to search the entire grid in all 8 directions and count how many times $word appears
function countWordInGrid($grid, $size, $word, $directions) {
    $wordLen = strlen($word);
    $count = 0;

    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            foreach ($directions as $dir) {
                $dr = $dir[0];
                $dc = $dir[1];

                // Calculate the final cell position for this direction
                $endRow = $r + ($wordLen - 1) * $dr;
                $endCol = $c + ($wordLen - 1) * $dc;

                // Skip if the word goes outside the grid boundaries
                if ($endRow < 0 || $endRow >= $size || $endCol < 0 || $endCol >= $size) {
                    continue;
                }

                // Check letter by letter
                $match = true;
                for ($k = 0; $k < $wordLen; $k++) {
                    if ($grid[$r + $k * $dr][$c + $k * $dc] !== $word[$k]) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    $count++;
                }
            }
        }
    }
    return $count;
}

// Function to generate a single valid puzzle
function generateOnePuzzle($size, $word, $directions) {
    $wordLen = strlen($word);
    $letters = str_split($word);
    $maxAttempts = 500; // Limit attempts to avoid infinite loops

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        // Step 1: Pick one of the 8 directions randomly
        $dir = $directions[array_rand($directions)];
        $dr = $dir[0];
        $dc = $dir[1];

        // Step 2: Find all valid starting locations where the word fits in this direction
        $validStarts = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $endRow = $r + ($wordLen - 1) * $dr;
                $endCol = $c + ($wordLen - 1) * $dc;
                if ($endRow >= 0 && $endRow < $size && $endCol >= 0 && $endCol < $size) {
                    $validStarts[] = [$r, $c];
                }
            }
        }

        if (empty($validStarts)) {
            continue;
        }

        // Pick a random starting point from valid positions
        $start = $validStarts[array_rand($validStarts)];
        $startRow = $start[0];
        $startCol = $start[1];

        // Step 3: Create an empty grid
        $grid = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $grid[$r][$c] = '';
            }
        }

        // Place the intentional word and remember its coordinates for the solution
        $solutionCoords = [];
        for ($i = 0; $i < $wordLen; $i++) {
            $currR = $startRow + ($i * $dr);
            $currC = $startCol + ($i * $dc);
            $grid[$currR][$currC] = $word[$i];
            $solutionCoords["$currR,$currC"] = true;
        }

        // Step 4: Fill remaining cells using shuffled groups of the input word letters
        // This guarantees letter frequencies match the original word without clustering
        $totalCells = $size * $size;
        $fillerNeeded = $totalCells - $wordLen;
        $fillerList = [];

        while (count($fillerList) < $fillerNeeded) {
            $shuffledGroup = $letters;
            shuffle($shuffledGroup);
            foreach ($shuffledGroup as $char) {
                $fillerList[] = $char;
                if (count($fillerList) === $fillerNeeded) {
                    break;
                }
            }
        }

        // Insert filler letters into empty cells
        $fillerIndex = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!isset($solutionCoords["$r,$c"])) {
                    $grid[$r][$c] = $fillerList[$fillerIndex];
                    $fillerIndex++;
                }
            }
        }

        // Step 5: Check if the word appears EXACTLY ONCE
        if (countWordInGrid($grid, $size, $word, $directions) === 1) {
            return [
                'grid' => $grid,
                'coords' => $solutionCoords
            ];
        }
    }

    return null; // Could not generate a valid puzzle within max attempts
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gridSize = isset($_POST['grid_size']) ? (int)$_POST['grid_size'] : 0;
    $numPuzzles = isset($_POST['puzzle_count']) ? (int)$_POST['puzzle_count'] : 0;
    $wordInput = isset($_POST['word']) ? trim($_POST['word']) : '';

    // Validation
    if ($gridSize < 5 || $gridSize > 20) {
        $errors[] = "Grid size must be between 5 and 20.";
    }

    if ($numPuzzles < 1) {
        $errors[] = "Number of puzzles must be at least 1.";
    }

    if (empty($wordInput)) {
        $errors[] = "Please enter a word.";
    } elseif (!ctype_alpha($wordInput)) {
        $errors[] = "The word must contain letters only (A-Z).";
    } else {
        $cleanWord = strtoupper($wordInput);
        if (strlen($cleanWord) > $gridSize) {
            $errors[] = "The word length (" . strlen($cleanWord) . ") cannot be larger than the grid size ($gridSize).";
        }
    }

    // Generate puzzles if validation passed
    if (empty($errors)) {
        for ($i = 0; $i < $numPuzzles; $i++) {
            $result = generateOnePuzzle($gridSize, $cleanWord, $directions);
            if ($result === null) {
                $errors[] = "Unable to generate puzzle #" . ($i + 1) . " with exactly 1 occurrence. Try a larger grid size.";
                $puzzles = [];
                break;
            }
            $puzzles[] = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Only One Word</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 30px 15px;
            color: #111827;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        h1, h2 {
            text-align: center;
        }

        .form-box {
            background: #ffffff;
            max-width: 450px;
            margin: 20px auto;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }

        input[type="number"], input[type="text"] {
            width: 100%;
            padding: 9px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #1d4ed8;
        }

        .error-box {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin: 15px auto;
            max-width: 450px;
            border: 1px solid #f87171;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
            margin-bottom: 40px;
        }

        .puzzle-item {
            background: #ffffff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .puzzle-item h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }

        table.puzzle-grid {
            border-collapse: collapse;
            margin: 0 auto;
        }

        table.puzzle-grid td {
            width: 30px;
            height: 30px;
            border: 1px solid #9ca3af;
            text-align: center;
            vertical-align: middle;
            font-family: monospace;
            font-size: 16px;
            font-weight: bold;
        }

        /* Green background required for solution cells */
        td.highlight-word {
            background-color: #4ade80 !important;
            color: #064e3b !important;
        }

        .section-title {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Only One Word</h1>

    <!-- JavaScript Validation Alerts -->
    <div id="jsErrors" class="error-box" style="display: none;"></div>

    <!-- PHP Server Validation Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- User Input Form -->
    <div class="form-box">
        <form method="POST" action="" onsubmit="return validateInput();">
            <div class="form-group">
                <label for="grid_size">Grid Size (5 to 20):</label>
                <input type="number" id="grid_size" name="grid_size" value="<?= htmlspecialchars($gridSize) ?>">
            </div>

            <div class="form-group">
                <label for="puzzle_count">Number of Puzzles:</label>
                <input type="number" id="puzzle_count" name="puzzle_count" value="<?= htmlspecialchars($numPuzzles) ?>">
            </div>

            <div class="form-group">
                <label for="word">Input Word:</label>
                <input type="text" id="word" name="word" value="<?= htmlspecialchars($wordInput) ?>" placeholder="e.g. BALLOON">
            </div>

            <button type="submit">Generate Puzzles</button>
        </form>
    </div>

    <?php if (!empty($puzzles)): ?>
        <!-- Puzzles Section -->
        <h2 class="section-title">Puzzles</h2>
        <div class="gallery">
            <?php foreach ($puzzles as $index => $item): ?>
                <div class="puzzle-item">
                    <h3>Puzzle <?= $index + 1 ?></h3>
                    <table class="puzzle-grid">
                        <?php foreach ($item['grid'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $char): ?>
                                    <td><?= htmlspecialchars($char) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Solutions Section -->
        <h2 class="section-title">Solutions</h2>
        <div class="gallery">
            <?php foreach ($puzzles as $index => $item): ?>
                <div class="puzzle-item">
                    <h3>Solution <?= $index + 1 ?></h3>
                    <table class="puzzle-grid">
                        <?php foreach ($item['grid'] as $r => $row): ?>
                            <tr>
                                <?php foreach ($row as $c => $char): ?>
                                    <?php $isHighlighted = isset($item['coords']["$r,$c"]); ?>
                                    <td class="<?= $isHighlighted ? 'highlight-word' : '' ?>">
                                        <?= htmlspecialchars($char) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Client-side validation using basic JavaScript
function validateInput() {
    var sizeInput = document.getElementById('grid_size').value;
    var countInput = document.getElementById('puzzle_count').value;
    var wordInput = document.getElementById('word').value.trim();
    var errorDiv = document.getElementById('jsErrors');

    var size = parseInt(sizeInput, 10);
    var count = parseInt(countInput, 10);
    var messages = [];

    if (isNaN(size) || size < 5 || size > 20) {
        messages.push("Grid size must be an integer between 5 and 20.");
    }

    if (isNaN(count) || count < 1) {
        messages.push("Number of puzzles must be at least 1.");
    }

    if (wordInput === "") {
        messages.push("Please enter a word.");
    } else if (!/^[a-zA-Z]+$/.test(wordInput)) {
        messages.push("The word must contain only letters (no numbers or spaces).");
    } else if (wordInput.length > size) {
        messages.push("The word length (" + wordInput.length + ") cannot be larger than the grid size (" + size + ").");
    }

    if (messages.length > 0) {
        var html = "<ul>";
        for (var i = 0; i < messages.length; i++) {
            html += "<li>" + messages[i] + "</li>";
        }
        html += "</ul>";
        errorDiv.innerHTML = html;
        errorDiv.style.display = "block";
        return false; // Prevent form submit
    }

    errorDiv.style.display = "none";
    return true; // Allow submit
}
</script>

</body>
</html>