<?php
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ANILIST_API_URL', 'https://graphql.anilist.co');

if (!defined('BOT_TOKEN') || BOT_TOKEN === false) {
    error_log("Error: Telegram BOT_TOKEN is not defined in environment variables.");
}
