<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Tokyo');

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'pinkclub_fanza';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'PinkClub FANZA';
const BASE_URL = '/';

const DMM_API_BASE_URL = 'https://api.dmm.com/affiliate/v3';
const DMM_FLOOR_LIST_ENDPOINT = '/FloorList';
const DMM_ITEM_LIST_ENDPOINT = '/ItemList';
const DMM_ACTRESS_SEARCH_ENDPOINT = '/ActressSearch';
const DMM_GENRE_SEARCH_ENDPOINT = '/GenreSearch';
const DMM_MAKER_SEARCH_ENDPOINT = '/MakerSearch';
const DMM_SERIES_SEARCH_ENDPOINT = '/SeriesSearch';
const DMM_AUTHOR_SEARCH_ENDPOINT = '/AuthorSearch';
