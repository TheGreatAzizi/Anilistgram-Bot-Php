<?php
// index.php
require_once 'config.php';

// Function to send a message to Telegram
function sendMessage($chatId, $text, $parseMode = 'MarkdownV2', $photoUrl = null, $replyMarkup = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';
    $data = [
        'chat_id' => $chatId,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];

    if ($photoUrl) {
        $url .= 'sendPhoto';
        $data['photo'] = $photoUrl;
        $data['caption'] = $text; // Caption for the photo
    } else {
        $url .= 'sendMessage';
        $data['text'] = $text;
    }

    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// Function to perform Anilist search by query (for /anime command)
function searchAnilistByQuery($query) {
    $graphqlQuery = '
        query ($search: String) {
            Media (search: $search, type: ANIME) {
                id
                title {
                    romaji
                    english
                    native
                }
                status
                episodes
                averageScore
                description
                siteUrl
                coverImage {
                    extraLarge
                }
                genres
                startDate {
                    year
                    month
                    day
                }
                format
                relations {
                    nodes {
                        id
                        type
                        title {
                            romaji
                            english
                        }
                    }
                    edges {
                        relationType
                    }
                }
            }
        }
    ';

    $variables = [
        'search' => $query
    ];

    $data = [
        'query' => $graphqlQuery,
        'variables' => $variables
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents(ANILIST_API_URL, false, $context);

    return json_decode($response, true);
}

// Function to perform Anilist search by ID (for callback queries)
function searchAnilistById($id) {
    $graphqlQuery = '
        query ($id: Int) {
            Media (id: $id, type: ANIME) {
                id
                title {
                    romaji
                    english
                    native
                }
                status
                episodes
                averageScore
                description
                siteUrl
                coverImage {
                    extraLarge
                }
                genres
                startDate {
                    year
                    month
                    day
                }
                format
                relations {
                    nodes {
                        id
                        type
                        title {
                            romaji
                            english
                        }
                    }
                    edges {
                        relationType
                    }
                }
            }
        }
    ';

    $variables = [
        'id' => $id
    ];

    $data = [
        'query' => $graphqlQuery,
        'variables' => $variables
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents(ANILIST_API_URL, false, $context);

    return json_decode($response, true);
}

// Function to format anime data into a message and create inline keyboard
function formatAnimeMessage($anime) {
    // Sanitize description for MarkdownV2
    $description = strip_tags($anime['description']); // Remove HTML tags
    $description = str_replace(['_'], ['\\_'], $description); // Escape underscores
    $description = str_replace(['*'], ['\\*'], $description); // Escape asterisks
    $description = str_replace(['`'], ['\\`'], $description); // Escape backticks
    $description = str_replace(['[', ']', '(', ')', '~', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'], ['\\[', '\\]', '\\(', '\\)', '\\~', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'], $description);

    // Get all available titles
    $titles = [];
    if (!empty($anime['title']['english'])) {
        $titles[] = "English: " . $anime['title']['english'];
    }
    if (!empty($anime['title']['romaji'])) {
        $titles[] = "Romaji: " . $anime['title']['romaji'];
    }
    if (!empty($anime['title']['native'])) {
        $titles[] = "Native: " . $anime['title']['native'];
    }
    $allTitles = empty($titles) ? 'N/A' : implode("\n", $titles);
    $allTitles = str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $allTitles); // Escape for Markdown

    // Map Anilist status to more user-friendly terms
    $statusMapping = [
        'FINISHED' => 'Finished',
        'RELEASING' => 'Releasing',
        'NOT_YET_RELEASED' => 'Not Yet Released',
        'CANCELLED' => 'Cancelled',
        'HIATUS' => 'Hiatus'
    ];
    $status = $statusMapping[$anime['status']] ?? 'N/A';

    // Map Anilist format to more user-friendly terms
    $formatMapping = [
        'TV' => 'TV Show',
        'TV_SHORT' => 'TV Short',
        'MOVIE' => 'Movie',
        'SPECIAL' => 'Special',
        'OVA' => 'OVA',
        'ONA' => 'ONA',
        'MUSIC' => 'Music Video',
        'MANGA' => 'Manga (Not an Anime)',
        'NOVEL' => 'Novel (Not an Anime)'
    ];
    $format = $formatMapping[$anime['format']] ?? 'N/A';

    $episodes = $anime['episodes'] ?? 'N/A';
    $score = $anime['averageScore'] ?? 'N/A';
    $siteUrl = $anime['siteUrl'] ?? 'N/A';
    $coverImage = $anime['coverImage']['extraLarge'] ?? null;

    $genres = 'N/A';
    if (!empty($anime['genres'])) {
        $genres = implode(', ', $anime['genres']);
        $genres = str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $genres);
    }

    $startDate = 'N/A';
    if (isset($anime['startDate']['year'])) {
        $startDate = $anime['startDate']['year'];
        if (isset($anime['startDate']['month'])) {
            $startDate .= '-' . str_pad($anime['startDate']['month'], 2, '0', STR_PAD_LEFT);
        }
        if (isset($anime['startDate']['day'])) {
            $startDate .= '-' . str_pad($anime['startDate']['day'], 2, '0', STR_PAD_LEFT);
        }
        $startDate = str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $startDate);
    }

    $messageText = "*Titles:*\n" . $allTitles . "\n";
    $messageText .= "*Status:* " . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $status) . "\n";
    $messageText .= "*Format:* " . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\~', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $format) . "\n";
    $messageText .= "*Episodes:* " . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $episodes) . "\n";
    $messageText .= "*Score:* " . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $score) . "\n";
    $messageText .= "*Genres:* " . $genres . "\n";
    $messageText .= "*Start Date:* " . $startDate . "\n";
    $messageText .= "*Description:* " . (mb_strlen($description) > 300 ? mb_substr($description, 0, 300) . "\\.\\.\\." : $description) . "\n";
    $messageText .= "[View on Anilist](" . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $siteUrl) . ")";

    $inlineKeyboard = [];
    if (!empty($anime['relations']['nodes'])) {
        foreach ($anime['relations']['nodes'] as $index => $node) {
            // Only add relations that are also ANIME type
            if (isset($node['type']) && $node['type'] === 'ANIME') {
                $relationType = $anime['relations']['edges'][$index]['relationType'] ?? 'RELATED'; // Default if not specified
                // Clean up relation type for display
                $relationType = str_replace('_', ' ', $relationType);
                $relationType = ucwords(strtolower($relationType)); // Capitalize first letter of each word

                $relationTitle = $node['title']['english'] ?? $node['title']['romaji'] ?? 'Unknown Title';
                // Only add button if the related anime title is available
                if ($relationTitle !== 'Unknown Title') {
                    // Maximum button text length for Telegram is 64 characters
                    $buttonText = $relationTitle . " (" . $relationType . ")";
                    if (mb_strlen($buttonText) > 60) { // Limit to 60 to be safe
                        $buttonText = mb_substr($buttonText, 0, 57) . "...";
                    }

                    $inlineKeyboard[] = [
                        'text' => $buttonText,
                        'callback_data' => 'anime_' . $node['id'] // Prefix with 'anime_' to identify callback data type
                    ];
                }
            }
        }
    }

    $replyMarkup = null;
    if (!empty($inlineKeyboard)) {
        // Group buttons into rows, e.g., 2 buttons per row
        $keyboardRows = [];
        $chunkedButtons = array_chunk($inlineKeyboard, 2); // 2 buttons per row
        foreach ($chunkedButtons as $row) {
            $keyboardRows[] = $row;
        }

        $replyMarkup = json_encode([
            'inline_keyboard' => $keyboardRows
        ]);
    }

    return ['messageText' => $messageText, 'coverImage' => $coverImage, 'replyMarkup' => $replyMarkup];
}


// Get the update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'];

    // Handle /anime command
    if (strpos($text, '/anime') === 0) {
        $parts = explode(' ', $text, 2);

        if (count($parts) < 2) {
            sendMessage($chatId, "Please provide an anime name after the command\\. Example: `\\/anime Naruto`");
            exit;
        }

        $searchQuery = $parts[1];
        sendMessage($chatId, "Searching for *" . str_replace(['-', '.', '!', '(', ')', '[', ']', '{', '}', '_', '`', '*', '~', '>', '#', '+', '=', '|', '.', '-'], ['\\-', '\\.', '\\!', '\\(', '\\)', '\\[', '\\]', '\\{', '\\}', '\\_', '\\`', '\\*', '\\~', '\\>', '\\#', '\\+', '\\=', '\\|', '\\.', '\\-'], $searchQuery) . "*\\.\\.\\.");

        $anilistData = searchAnilistByQuery($searchQuery);

        if (isset($anilistData['data']['Media']) && $anilistData['data']['Media'] !== null) {
            $anime = $anilistData['data']['Media'];
            $formattedData = formatAnimeMessage($anime);
            sendMessage($chatId, $formattedData['messageText'], 'MarkdownV2', $formattedData['coverImage'], $formattedData['replyMarkup']);
        } else {
            sendMessage($chatId, "No anime found with that title\\.");
        }
    }
} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    $messageId = $callbackQuery['message']['message_id']; // To edit existing message

    // Answer the callback query to remove the loading animation from the button
    $answerCallbackUrl = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery';
    $answerCallbackData = [
        'callback_query_id' => $callbackQuery['id'],
        'text' => 'Fetching anime details...', // Optional: show a small popup to the user
        'show_alert' => false
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($answerCallbackData),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($answerCallbackUrl, false, $context);


    // Check if the callback data is for an anime search by ID
    if (strpos($data, 'anime_') === 0) {
        $animeId = (int)str_replace('anime_', '', $data);

        // Notify user that we are processing
        // You might want to edit the message to show "Loading..." here
        // For simplicity, we just fetch data and send new message (or edit)

        $anilistData = searchAnilistById($animeId);

        if (isset($anilistData['data']['Media']) && $anilistData['data']['Media'] !== null) {
            $anime = $anilistData['data']['Media'];
            $formattedData = formatAnimeMessage($anime);

            // Send a new message with the anime details
            // Alternatively, you can edit the previous message if you prefer
            sendMessage($chatId, $formattedData['messageText'], 'MarkdownV2', $formattedData['coverImage'], $formattedData['replyMarkup']);

        } else {
            sendMessage($chatId, "Could not find details for this anime\\.");
        }
    }
}
?>
