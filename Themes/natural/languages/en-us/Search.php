<?php

/**
 * This file contains language strings for the search system.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

$txt['set_parameters'] = 'Set Search Parameters';
$txt['choose_board'] = 'Choose a board to search in, or search all';
$txt['all_words'] = 'Match all words';
$txt['any_words'] = 'Match any words';
$txt['by_user'] = 'By user';

$txt['search_post_age'] = 'Message age';
$txt['search_between'] = 'between';
$txt['search_and'] = 'and';
$txt['search_options'] = 'Options';
$txt['search_show_complete_messages'] = 'Show results as messages';
$txt['search_subject_only'] = 'Search in topic subjects only';
$txt['search_relevance'] = 'Relevance';
$txt['search_date_posted'] = 'Date Posted';
$txt['search_order'] = 'Search order';
$txt['search_orderby_relevant_first'] = 'Most relevant results first';
$txt['search_orderby_large_first'] = 'Largest topics first';
$txt['search_orderby_small_first'] = 'Smallest topics first';
$txt['search_orderby_recent_first'] = 'Most recent topics first';
$txt['search_orderby_old_first'] = 'Oldest topics first';
$txt['search_visual_verification_label'] = 'Verification';
$txt['search_visual_verification_desc'] = 'Please enter the code in the image above to use search.';

$txt['search_specific_topic'] = 'Searching only posts in the topic';

$txt['mods_cat_search'] = 'Search';
$txt['search_results_per_page'] = 'Number of search results per page';

$txt['search_settings_desc'] = 'Here you can changes the basic settings of the search function.';
$txt['search_settings_title'] = 'Search Settings';

$txt['search_method_desc'] = 'Here you can set the way search is powered.';
$txt['search_method_title'] = 'Search - method';
$txt['search_method_save'] = 'Save';
$txt['search_method_messages_table_space'] = 'Space used by forum messages in the database';
$txt['search_method_messages_index_space'] = 'Space used to index messages in the database';
$txt['search_method_kilobytes'] = 'KB';
$txt['search_method_fulltext_index'] = 'Fulltext index';
$txt['search_method_no_index_exists'] = 'doesn\'t currently exist';
$txt['search_method_fulltext_create'] = 'create a fulltext index';
$txt['search_method_fulltext_cannot_create'] = 'cannot be created because the max message length is above 65,535 or table type is not MyISAM';
$txt['search_method_index_already_exists'] = 'already created';
$txt['search_method_fulltext_remove'] = 'remove fulltext index';
// This string is used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'In order to be able to use fulltext search, you\\\'ll have to create a fulltext index first.';

$txt['search_index'] = 'Search index';
$txt['search_index_none'] = 'No index';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Size';

$txt['search_force_index'] = 'Force the use of a search index';
$txt['search_match_words'] = 'Match whole words only';
$txt['search_max_results'] = 'Maximum results to show';
$txt['search_max_results_disable'] = '(0: no limit)';
$txt['search_floodcontrol_time'] = 'Time required between searches from same user';
$txt['search_floodcontrol_time_desc'] = '(0 for no limit, in seconds)';

$txt['error_string_too_long'] = 'Search string must be less than %1$d characters long.';

$txt['search_adjust_query'] = 'Adjust Search Parameters';
$txt['search_warning_ignored_word'] = 'The following term has been ignored in your search because it is too short';
$txt['search_warning_ignored_words'] = 'The following terms have been ignored in your search because they are too short';
$txt['search_adjust_submit'] = 'Revise Search';

$txt['search_example'] = '<em>e.g.</em> Orwell "Animal Farm" -movie';
