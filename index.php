<?php
/**
 * Plugin Name: LLMs.txt Generator for WP
 * Plugin URI: https://github.com/KuboFumiaki/llms-txt-generator-wp
 * Description: WordPressサイトのコンテンツからLLMS.txtファイルを自動生成するプラグインです。投稿、カスタム投稿タイプ、カテゴリ情報を含むマークダウン形式のファイルを生成し、LLMsがサイト内容を理解するのに役立ちます。
 * Version: 1.0.0
 * Author: Kubo Fumiaki
 * Author URI:
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: llms-txt-generator-wp
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LLMSTXTGEN_VERSION', '1.0.0');

// プラグインの有効化時に実行される処理
function llmstxtgen_activate() {
    // デフォルト設定を作成
    if (!get_option('llmstxtgen_encoding')) {
        add_option('llmstxtgen_encoding', 'UTF-8');
    }

    if (!get_option('llmstxtgen_custom_text')) {
        $default_text = "# " . get_bloginfo('name') . "\n\n" . get_bloginfo('description') . "\n\n";
        add_option('llmstxtgen_custom_text', $default_text);
    }

    if (!get_option('llmstxtgen_post_type_settings')) {
        // 公開されている投稿タイプをデフォルトで全て有効にする
        $public_post_types = get_post_types(array('public' => true), 'names');
        unset($public_post_types['page'], $public_post_types['attachment']);
        add_option('llmstxtgen_post_type_settings', array(
            'enabled' => array_values($public_post_types),
            'order' => array()
        ));
    }

    if (!get_option('llmstxtgen_page_settings')) {
        // 全ての固定ページをデフォルトで有効にする
        $all_pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        $default_enabled_pages = array();
        foreach ($all_pages as $page) {
            $default_enabled_pages[] = $page->ID;
        }
        add_option('llmstxtgen_page_settings', array(
            'enabled_pages' => $default_enabled_pages,
            'order' => array()
        ));
    }

    // 初回のLLMS.txtファイルを生成
    llmstxtgen_generate();
}
register_activation_hook(__FILE__, 'llmstxtgen_activate');

// プラグインの無効化時に実行される処理
function llmstxtgen_deactivate() {
    // 設定データは残すが、スケジュールされたイベントがあれば削除
    wp_clear_scheduled_hook('llmstxtgen_cron');
}
register_deactivation_hook(__FILE__, 'llmstxtgen_deactivate');

// LLMS.txtを生成してサイトルートに保存する
function llmstxtgen_generate() {
    // 公開されている投稿タイプのみを対象にする（内部用投稿タイプの内容が漏れるのを防ぐ）
    $public_post_types = get_post_types(array('public' => true), 'names');
    unset($public_post_types['attachment']);

    // 全投稿記事を取得
    $all_posts = get_posts(array(
        'numberposts' => -1,
        'post_status' => 'publish',
        'post_type' => array_values($public_post_types),
        'orderby' => 'post_type',
        'order' => 'ASC'
    ));

    $content = '';

    // カスタムテキストがあれば上部に表示
    $custom_text = get_option('llmstxtgen_custom_text', '');
    if (!empty($custom_text)) {
        $content .= $custom_text . "\n\n";
    }

    /* translators: LLMS.txt内の最終更新日時の見出し */
    $content .= "# " . __('最終更新', 'llms-txt-generator-wp') . ": " . gmdate('Y-m-d H:i:s') . "\n\n";

    // 投稿タイプの設定を取得
    $post_type_settings = get_option('llmstxtgen_post_type_settings', array());
    $enabled_post_types = isset($post_type_settings['enabled']) ? $post_type_settings['enabled'] : array();
    $post_type_order = isset($post_type_settings['order']) ? $post_type_settings['order'] : array();

    // 固定ページの設定を取得
    $page_settings = get_option('llmstxtgen_page_settings', array());
    $enabled_pages = isset($page_settings['enabled_pages']) ? $page_settings['enabled_pages'] : array();
    $page_order = isset($page_settings['order']) ? $page_settings['order'] : array();

    // 投稿タイプ別に記事を分類
    $posts_by_type = array();
    $pages = array(); // 固定ページ専用配列

    foreach ($all_posts as $post) {
        $post_type = get_post_type($post->ID);

        // 固定ページの処理（選択されたもののみ出力）
        if ($post_type === 'page') {
            if (in_array($post->ID, $enabled_pages)) {
                $pages[] = $post;
            }
            continue;
        }

        // 出力対象に設定された投稿タイプのみ出力
        if (!in_array($post_type, $enabled_post_types, true)) {
            continue;
        }

        if (!isset($posts_by_type[$post_type])) {
            $posts_by_type[$post_type] = array();
        }
        $posts_by_type[$post_type][] = $post;
    }

    // カスタム順序で投稿タイプを並び替え
    if (!empty($post_type_order)) {
        $ordered_posts_by_type = array();

        // 順序設定に従って並び替え
        foreach ($post_type_order as $post_type) {
            if (isset($posts_by_type[$post_type])) {
                $ordered_posts_by_type[$post_type] = $posts_by_type[$post_type];
                unset($posts_by_type[$post_type]);
            }
        }

        // 順序設定にない投稿タイプを最後に追加
        $posts_by_type = array_merge($ordered_posts_by_type, $posts_by_type);
    }

    // 固定ページの処理（投稿よりも先に出力）
    if (!empty($pages)) {
        $content .= "## " . __('固定ページ', 'llms-txt-generator-wp') . "\n";

        // ページをIDでインデックス化
        $pages_by_id = array();
        foreach ($pages as $page) {
            $pages_by_id[$page->ID] = $page;
        }

        // 親ページと子ページを分離
        $parent_pages = array();
        $child_pages_by_parent = array();

        foreach ($pages as $page) {
            if ($page->post_parent == 0) {
                // 親ページ
                $parent_pages[] = $page;
            } else {
                // 子ページ
                if (!isset($child_pages_by_parent[$page->post_parent])) {
                    $child_pages_by_parent[$page->post_parent] = array();
                }
                $child_pages_by_parent[$page->post_parent][] = $page;
            }
        }

        // 親ページの順序設定に従って並び替え
        $ordered_parent_pages = array();
        $parent_pages_by_id = array();

        foreach ($parent_pages as $page) {
            $parent_pages_by_id[$page->ID] = $page;
        }

        // 順序設定に従って親ページを並び替え
        if (!empty($page_order)) {
            foreach ($page_order as $page_id) {
                if (isset($parent_pages_by_id[$page_id])) {
                    $ordered_parent_pages[] = $parent_pages_by_id[$page_id];
                    unset($parent_pages_by_id[$page_id]);
                }
            }
        }

        // 順序設定にない親ページを最後に追加
        $ordered_parent_pages = array_merge($ordered_parent_pages, array_values($parent_pages_by_id));

        // 順序設定にない子ページも追加（親ページがない子ページ）
        $orphan_child_pages = array();
        if (!empty($page_order)) {
            foreach ($page_order as $page_id) {
                if (isset($pages_by_id[$page_id]) && $pages_by_id[$page_id]->post_parent != 0) {
                    // 親ページが選択されていない子ページを個別に出力
                    if (!isset($parent_pages_by_id[$pages_by_id[$page_id]->post_parent]) &&
                        !in_array($pages_by_id[$page_id], $orphan_child_pages)) {
                        $orphan_child_pages[] = $pages_by_id[$page_id];
                    }
                }
            }
        }

        // 親ページとその子ページを出力（親ページの順番に紐づけて、子ページは公開日順）
        foreach ($ordered_parent_pages as $parent_page) {
            $page_url = get_permalink($parent_page->ID);
            $excerpt = wp_trim_words($parent_page->post_content, 15, '...');
            $content .= "- [{$parent_page->post_title}]({$page_url}):{$excerpt}\n";

            // この親ページの子ページがあれば公開日順で出力
            if (isset($child_pages_by_parent[$parent_page->ID])) {
                $child_pages = $child_pages_by_parent[$parent_page->ID];

                // 子ページを公開日順でソート
                usort($child_pages, function($a, $b) {
                    return strcmp($a->post_date, $b->post_date);
                });

                // 子ページを出力
                foreach ($child_pages as $child_page) {
                    $child_url = get_permalink($child_page->ID);
                    $child_excerpt = wp_trim_words($child_page->post_content, 15, '...');
                    $content .= "  - [{$child_page->post_title}]({$child_url}):{$child_excerpt}\n";
                }
            }
        }

        // 親ページが選択されていない独立した子ページを出力
        foreach ($orphan_child_pages as $orphan_page) {
            $page_url = get_permalink($orphan_page->ID);
            $excerpt = wp_trim_words($orphan_page->post_content, 15, '...');
            $content .= "- [{$orphan_page->post_title}]({$page_url}):{$excerpt}\n";
        }

        $content .= "\n";
    }

    // 投稿タイプ別に出力
    foreach ($posts_by_type as $post_type => $posts) {
        $post_type_object = get_post_type_object($post_type);
        $post_type_name = $post_type_object ? $post_type_object->labels->name : $post_type;

        $content .= "## {$post_type_name}\n";

        // 一覧（アーカイブ）ページがあれば見出しの直下に出力
        if ($post_type !== 'post') {
            $archive_link = get_post_type_archive_link($post_type);
            if ($archive_link) {
                /* translators: %s: 投稿タイプ名 */
                $archive_label = sprintf(__('%s一覧', 'llms-txt-generator-wp'), $post_type_name);
                $content .= "- [{$archive_label}]({$archive_link})\n";
            }
        }

        // 通常の投稿（post）の場合はカテゴリ別に分類
        if ($post_type === 'post') {
            $posts_by_category = array();
            $uncategorized_posts = array();

            foreach ($posts as $post) {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        if (!isset($posts_by_category[$category->name])) {
                            $posts_by_category[$category->name] = array();
                        }
                        $posts_by_category[$category->name][] = $post;
                        break; // 最初のカテゴリのみ使用
                    }
                } else {
                    $uncategorized_posts[] = $post;
                }
            }

            // カテゴリ別に出力
            foreach ($posts_by_category as $category_name => $category_posts) {
                $content .= "### {$category_name}\n";
                foreach ($category_posts as $post) {
                    $post_url = get_permalink($post->ID);
                    $excerpt = wp_trim_words($post->post_content, 15, '...');
                    $content .= "- [{$post->post_title}]({$post_url}):{$excerpt}\n";
                }
                $content .= "\n";
            }

            // 未分類の投稿
            if (!empty($uncategorized_posts)) {
                $content .= "### " . __('未分類', 'llms-txt-generator-wp') . "\n";
                foreach ($uncategorized_posts as $post) {
                    $post_url = get_permalink($post->ID);
                    $excerpt = wp_trim_words($post->post_content, 15, '...');
                    $content .= "- [{$post->post_title}]({$post_url}):{$excerpt}\n";
                }
                $content .= "\n";
            }
        } else {
            // その他の投稿タイプはそのまま出力
            foreach ($posts as $post) {
                $post_url = get_permalink($post->ID);
                $excerpt = wp_trim_words($post->post_content, 15, '...');
                $content .= "- [{$post->post_title}]({$post_url}):{$excerpt}\n";
            }
            $content .= "\n";
        }
    }

    // 選択された文字コードに変換（デフォルトはUTF-8）
    $encoding = get_option('llmstxtgen_encoding', 'UTF-8');
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'SJIS', 'UTF-8');
    }

    // WP_Filesystem APIでサイトルートに保存
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!WP_Filesystem()) {
        return false;
    }

    return (bool) $wp_filesystem->put_contents(ABSPATH . 'llms.txt', $content, FS_CHMOD_FILE);
}

// LLMS.txtの再生成を予約する（1リクエスト内で複数フックが発火しても、shutdownで1回だけ生成する）
function llmstxtgen_schedule_generate() {
    static $scheduled = false;
    if ($scheduled) {
        return;
    }
    $scheduled = true;
    add_action('shutdown', 'llmstxtgen_generate');
}

// 投稿・固定ページ保存時の処理
function llmstxtgen_handle_save_post($post_id, $post, $update) {
    // 自動保存、リビジョンはスキップ
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // 公開状態の投稿のみ処理
    if ($post->post_status !== 'publish') {
        return;
    }

    // 出力対象リストにない固定ページが公開されたら自動で出力対象に追加
    if ($post->post_type === 'page') {
        $page_settings = get_option('llmstxtgen_page_settings', array());
        $enabled_pages = isset($page_settings['enabled_pages']) ? $page_settings['enabled_pages'] : array();

        if (!in_array($post_id, $enabled_pages)) {
            $enabled_pages[] = $post_id;

            $page_order = isset($page_settings['order']) ? $page_settings['order'] : array();
            array_unshift($page_order, $post_id);

            $page_settings['enabled_pages'] = $enabled_pages;
            $page_settings['order'] = $page_order;
            update_option('llmstxtgen_page_settings', $page_settings);
        }
    }

    llmstxtgen_schedule_generate();
}
add_action('save_post', 'llmstxtgen_handle_save_post', 10, 3);

// ステータス変更時（下書き→公開、公開→下書き、ゴミ箱への移動など）の処理
function llmstxtgen_handle_status_change($new_status, $old_status, $post) {
    // 公開に関わる変更の場合のみ再生成
    if ($new_status === 'publish' || $old_status === 'publish') {
        llmstxtgen_schedule_generate();
    }
}
add_action('transition_post_status', 'llmstxtgen_handle_status_change', 10, 3);

// 投稿・固定ページ削除完了後の処理
function llmstxtgen_handle_post_delete($post_id) {
    llmstxtgen_schedule_generate();
}
add_action('deleted_post', 'llmstxtgen_handle_post_delete');

// 管理画面メニューを追加
function llmstxtgen_register_admin_menu() {
    add_management_page(
        __('LLMs.txt Generator for WP', 'llms-txt-generator-wp'),
        __('LLMs.txt Generator for WP', 'llms-txt-generator-wp'),
        'manage_options',
        'llmstxtgen',
        'llmstxtgen_admin_page'
    );
}
add_action('admin_menu', 'llmstxtgen_register_admin_menu');

// 管理画面用スクリプトの読み込み（プラグイン設定ページのみ）
function llmstxtgen_enqueue_admin_assets($hook_suffix) {
    if ($hook_suffix !== 'tools_page_llmstxtgen') {
        return;
    }

    wp_enqueue_script(
        'llmstxtgen-admin',
        plugins_url('js/admin.js', __FILE__),
        array('jquery'),
        LLMSTXTGEN_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'llmstxtgen_enqueue_admin_assets');

// 固定ページを階層構造で表示する
function llmstxtgen_display_page_tree($pages, $enabled_pages, $parent_id = 0, $level = 0) {
    $child_pages = array();
    foreach ($pages as $page) {
        if ($page->post_parent == $parent_id) {
            $child_pages[] = $page;
        }
    }

    foreach ($child_pages as $page) {
        echo '<label style="display: block; margin-bottom: 5px; margin-left: ' . esc_attr($level * 24) . 'px;">';
        echo '<input type="checkbox" name="enabled_pages[]" value="' . esc_attr($page->ID) . '"' . checked(in_array($page->ID, $enabled_pages), true, false) . '>';
        echo ' ' . esc_html($page->post_title);
        if ($level == 0) {
            echo ' <span style="color: #999; font-size: 12px;">(' . esc_html__('親ページ', 'llms-txt-generator-wp') . ')</span>';
        } else {
            echo ' <span style="color: #999; font-size: 12px;">(' . esc_html__('子ページ', 'llms-txt-generator-wp') . ')</span>';
        }
        echo '</label>';

        // 再帰的に子ページを表示
        llmstxtgen_display_page_tree($pages, $enabled_pages, $page->ID, $level + 1);
    }
}

// 管理画面の設定ページ
function llmstxtgen_admin_page() {
    // 権限チェック
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('このページにアクセスする権限がありません。', 'llms-txt-generator-wp'));
    }

    $file_path = ABSPATH . 'llms.txt';
    $file_url = get_site_url() . '/llms.txt';

    // カスタムテキストの保存処理
    if (isset($_POST['save_custom_text']) && check_admin_referer('llmstxtgen_custom_text_action', 'llmstxtgen_custom_text_nonce')) {
        if (isset($_POST['llmstxtgen_custom_text'])) {
            $custom_text = sanitize_textarea_field(wp_unslash($_POST['llmstxtgen_custom_text']));
            update_option('llmstxtgen_custom_text', $custom_text);
            echo '<div class="notice notice-success"><p>' . esc_html__('カスタムテキストが保存されました！', 'llms-txt-generator-wp') . '</p></div>';
        }
    }

    // 文字コード設定の保存処理
    if (isset($_POST['save_encoding']) && check_admin_referer('llmstxtgen_encoding_action', 'llmstxtgen_encoding_nonce')) {
        $encoding = (isset($_POST['llmstxtgen_encoding']) && sanitize_text_field(wp_unslash($_POST['llmstxtgen_encoding'])) === 'SJIS') ? 'SJIS' : 'UTF-8';
        update_option('llmstxtgen_encoding', $encoding);
        echo '<div class="notice notice-success"><p>' . esc_html__('文字コード設定が保存されました！', 'llms-txt-generator-wp') . '</p></div>';
    }

    // 投稿タイプ設定の保存処理
    if (isset($_POST['save_post_types']) && check_admin_referer('llmstxtgen_post_types_action', 'llmstxtgen_post_types_nonce')) {
        $enabled_post_types = isset($_POST['enabled_post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['enabled_post_types'])) : array();
        $post_type_order_raw = isset($_POST['post_type_order']) ? sanitize_text_field(wp_unslash($_POST['post_type_order'])) : '';

        if (!empty($post_type_order_raw)) {
            $post_type_order = array_map('sanitize_text_field', explode(',', $post_type_order_raw));
            $post_type_order = array_filter($post_type_order); // 空の要素を除去
        } else {
            $post_type_order = array();
        }

        update_option('llmstxtgen_post_type_settings', array(
            'enabled' => $enabled_post_types,
            'order' => $post_type_order
        ));
        echo '<div class="notice notice-success"><p>' . esc_html__('投稿タイプ設定が保存されました！', 'llms-txt-generator-wp') . '</p></div>';
    }

    // 固定ページ設定の保存処理
    if (isset($_POST['save_page_settings']) && check_admin_referer('llmstxtgen_page_settings_action', 'llmstxtgen_page_settings_nonce')) {
        $enabled_pages = isset($_POST['enabled_pages']) ? array_map('intval', wp_unslash($_POST['enabled_pages'])) : array();
        $page_order_raw = isset($_POST['page_order']) ? sanitize_text_field(wp_unslash($_POST['page_order'])) : '';

        if (!empty($page_order_raw)) {
            $page_order = array_map('intval', explode(',', $page_order_raw));
            $page_order = array_filter($page_order); // 空の要素を除去
        } else {
            $page_order = array();
        }

        update_option('llmstxtgen_page_settings', array(
            'enabled_pages' => $enabled_pages,
            'order' => $page_order
        ));
        echo '<div class="notice notice-success"><p>' . esc_html__('固定ページ設定が保存されました！', 'llms-txt-generator-wp') . '</p></div>';
    }

    // 手動生成処理
    if (isset($_POST['generate_llms']) && check_admin_referer('llmstxtgen_generate_action', 'llmstxtgen_generate_nonce')) {
        if (llmstxtgen_generate()) {
            echo '<div class="notice notice-success"><p>' . esc_html__('LLMS.txtが生成されました！', 'llms-txt-generator-wp') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('LLMS.txtの生成に失敗しました。サイトルートへの書き込み権限を確認してください。', 'llms-txt-generator-wp') . '</p></div>';
        }
    }

    $current_custom_text = get_option('llmstxtgen_custom_text', '');
    $current_encoding = get_option('llmstxtgen_encoding', 'UTF-8');
    $post_type_settings = get_option('llmstxtgen_post_type_settings', array());
    $enabled_post_types = isset($post_type_settings['enabled']) ? $post_type_settings['enabled'] : array();
    $post_type_order = isset($post_type_settings['order']) ? $post_type_settings['order'] : array();

    // 固定ページ設定を取得
    $page_settings = get_option('llmstxtgen_page_settings', array());
    $enabled_pages = isset($page_settings['enabled_pages']) ? $page_settings['enabled_pages'] : array();
    $page_order = isset($page_settings['order']) ? $page_settings['order'] : array();

    // 利用可能な投稿タイプを取得（固定ページと添付ファイルを除外）
    $available_post_types = array();
    $all_post_types = get_post_types(array('public' => true), 'objects');
    foreach ($all_post_types as $post_type_key => $post_type_obj) {
        if ($post_type_key !== 'page' && $post_type_key !== 'attachment') {
            $available_post_types[$post_type_key] = $post_type_obj;
        }
    }

    // 利用可能な固定ページを取得（すべての公開済み固定ページ）
    $available_pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('LLMs.txt Generator for WP', 'llms-txt-generator-wp') . '</h1>';

    // カスタムテキスト設定フォーム
    echo '<h2>' . esc_html__('カスタムテキスト設定', 'llms-txt-generator-wp') . '</h2>';
    echo '<p>' . esc_html__('LLMS.txtファイルの上部に表示するテキストを設定できます。', 'llms-txt-generator-wp') . '</p>';
    echo '<form method="post" style="margin-bottom: 30px;">';
    wp_nonce_field('llmstxtgen_custom_text_action', 'llmstxtgen_custom_text_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('カスタムテキスト', 'llms-txt-generator-wp') . '</th>';
    echo '<td>';
    echo '<textarea name="llmstxtgen_custom_text" rows="8" cols="80" class="large-text">' . esc_textarea($current_custom_text) . '</textarea>';
    echo '<p class="description">' . esc_html__('Markdownフォーマットで記述してください。このテキストはLLMS.txtの最上部に表示されます。', 'llms-txt-generator-wp') . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="save_custom_text" class="button button-primary" value="' . esc_attr__('カスタムテキストを保存', 'llms-txt-generator-wp') . '">';
    echo '</p>';
    echo '</form>';

    echo '<hr>';

    // 文字コード設定フォーム
    echo '<h2>' . esc_html__('文字コード設定', 'llms-txt-generator-wp') . '</h2>';
    echo '<p>' . esc_html__('LLMS.txtファイルの文字コードを選択してください。', 'llms-txt-generator-wp') . '</p>';
    echo '<form method="post" style="margin-bottom: 30px;">';
    wp_nonce_field('llmstxtgen_encoding_action', 'llmstxtgen_encoding_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('ファイル文字コード', 'llms-txt-generator-wp') . '</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="radio" name="llmstxtgen_encoding" value="UTF-8"' . checked($current_encoding, 'UTF-8', false) . '>';
    echo ' UTF-8';
    echo '</label><br>';
    echo '<label>';
    echo '<input type="radio" name="llmstxtgen_encoding" value="SJIS"' . checked($current_encoding, 'SJIS', false) . '>';
    echo ' Shift-JIS';
    echo '</label>';
    echo '<p class="description">' . esc_html__('ファイルを保存する際の文字コードを選択してください。デフォルトはUTF-8です。', 'llms-txt-generator-wp') . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="save_encoding" class="button button-primary" value="' . esc_attr__('文字コード設定を保存', 'llms-txt-generator-wp') . '">';
    echo '</p>';
    echo '</form>';

    echo '<hr>';

    // 投稿タイプ設定フォーム
    echo '<h2>' . esc_html__('投稿タイプ設定', 'llms-txt-generator-wp') . '</h2>';
    echo '<p>' . esc_html__('LLMS.txtに出力する投稿タイプと順番を設定してください。', 'llms-txt-generator-wp') . '</p>';
    echo '<form method="post" style="margin-bottom: 30px;">';
    wp_nonce_field('llmstxtgen_post_types_action', 'llmstxtgen_post_types_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('出力する投稿タイプ', 'llms-txt-generator-wp') . '</th>';
    echo '<td>';

    if (!empty($available_post_types)) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<p><strong>' . esc_html__('チェックした投稿タイプのみが出力されます：', 'llms-txt-generator-wp') . '</strong></p>';
        foreach ($available_post_types as $post_type_key => $post_type_obj) {
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="enabled_post_types[]" value="' . esc_attr($post_type_key) . '"' . checked(in_array($post_type_key, $enabled_post_types, true), true, false) . '>';
            echo ' ' . esc_html($post_type_obj->labels->name) . ' (' . esc_html($post_type_key) . ')';
            echo '</label>';
        }
        echo '</div>';

        echo '<div>';
        echo '<p><strong>' . esc_html__('出力順序：', 'llms-txt-generator-wp') . '</strong></p>';
        echo '<p class="description">' . esc_html__('上下の矢印ボタンをクリックして順番を変更してください。', 'llms-txt-generator-wp') . '</p>';
        echo '<div id="post-type-order-list" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; min-height: 120px;">';

        // 全ての投稿タイプをリストアップ（順序設定に基づいて）
        $ordered_types = array();
        if (!empty($post_type_order)) {
            foreach ($post_type_order as $post_type_key) {
                if (isset($available_post_types[$post_type_key])) {
                    $ordered_types[] = $post_type_key;
                }
            }
        }

        // 順序設定にない投稿タイプを最後に追加
        foreach ($available_post_types as $post_type_key => $post_type_obj) {
            if (!in_array($post_type_key, $ordered_types)) {
                $ordered_types[] = $post_type_key;
            }
        }

        foreach ($ordered_types as $index => $post_type_key) {
            if (isset($available_post_types[$post_type_key])) {
                $post_type_obj = $available_post_types[$post_type_key];
                $is_first = ($index === 0);
                $is_last = ($index === count($ordered_types) - 1);

                echo '<div class="post-type-item" data-post-type="' . esc_attr($post_type_key) . '" style="display: flex; align-items: center; padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #ccc; border-radius: 3px;">';

                // 投稿タイプ名
                echo '<span style="flex: 1; font-weight: 500;">';
                echo esc_html($post_type_obj->labels->name) . ' (' . esc_html($post_type_key) . ')';
                echo '</span>';

                // 矢印ボタン
                echo '<div style="margin-left: 10px;">';
                echo '<button type="button" class="move-up button button-small"' . disabled($is_first, true, false) . ' style="margin-right: 5px;" data-post-type="' . esc_attr($post_type_key) . '">&uarr;</button>';
                echo '<button type="button" class="move-down button button-small"' . disabled($is_last, true, false) . ' data-post-type="' . esc_attr($post_type_key) . '">&darr;</button>';
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '<input type="hidden" name="post_type_order" id="post_type_order" value="' . esc_attr(implode(',', $ordered_types)) . '">';
        echo '</div>';
    } else {
        echo '<p>' . esc_html__('投稿タイプが見つかりませんでした。', 'llms-txt-generator-wp') . '</p>';
    }

    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="save_post_types" class="button button-primary" value="' . esc_attr__('投稿タイプ設定を保存', 'llms-txt-generator-wp') . '">';
    echo '</p>';
    echo '</form>';

    echo '<hr>';

    // 固定ページ出力設定フォーム
    echo '<h2>' . esc_html__('固定ページ出力設定', 'llms-txt-generator-wp') . '</h2>';
    echo '<p>' . esc_html__('LLMS.txtに出力する固定ページと順番を設定してください。', 'llms-txt-generator-wp') . '</p>';
    echo '<form method="post" style="margin-bottom: 30px;">';
    wp_nonce_field('llmstxtgen_page_settings_action', 'llmstxtgen_page_settings_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('出力する固定ページ', 'llms-txt-generator-wp') . '</th>';
    echo '<td>';

    if (!empty($available_pages)) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<p><strong>' . esc_html__('チェックした固定ページのみが出力されます：', 'llms-txt-generator-wp') . '</strong></p>';

        // 一括選択ボタン
        echo '<div style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-radius: 3px;">';
        echo '<button type="button" id="check-all-pages" class="button button-secondary" style="margin-right: 10px;">' . esc_html__('すべて選択', 'llms-txt-generator-wp') . '</button>';
        echo '<button type="button" id="uncheck-all-pages" class="button button-secondary">' . esc_html__('すべて解除', 'llms-txt-generator-wp') . '</button>';
        echo '</div>';

        llmstxtgen_display_page_tree($available_pages, $enabled_pages);
        echo '</div>';

        echo '<div>';
        echo '<p><strong>' . esc_html__('出力順序：', 'llms-txt-generator-wp') . '</strong></p>';
        echo '<p class="description">' . esc_html__('上下の矢印ボタンをクリックして順番を変更してください。選択した固定ページのみが表示されます。', 'llms-txt-generator-wp') . '</p>';
        echo '<div id="page-order-list" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; min-height: 120px;">';

        // 有効な親ページのみの順序設定（子ページは表示しない）
        $enabled_parent_pages = array();
        foreach ($available_pages as $page) {
            // enabledリストに含まれる親ページのみ
            if (in_array($page->ID, $enabled_pages) && $page->post_parent == 0) {
                $enabled_parent_pages[] = $page;
            }
        }

        $ordered_pages = array();
        if (!empty($page_order)) {
            foreach ($page_order as $page_id) {
                foreach ($enabled_parent_pages as $page) {
                    if ($page->ID == $page_id) {
                        $ordered_pages[] = $page;
                        break;
                    }
                }
            }
        }

        // 順序設定にない有効な親ページを最後に追加
        foreach ($enabled_parent_pages as $page) {
            $already_ordered = false;
            foreach ($ordered_pages as $ordered_page) {
                if ($ordered_page->ID == $page->ID) {
                    $already_ordered = true;
                    break;
                }
            }
            if (!$already_ordered) {
                $ordered_pages[] = $page;
            }
        }

        foreach ($ordered_pages as $index => $page) {
            $is_first = ($index === 0);
            $is_last = ($index === count($ordered_pages) - 1);

            echo '<div class="page-item" data-page-id="' . esc_attr($page->ID) . '" style="display: flex; align-items: center; padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #ccc; border-radius: 3px;">';

            // 親ページタイトルのみ表示
            echo '<span style="flex: 1; font-weight: 500;">';
            echo esc_html($page->post_title) . ' (ID: ' . esc_html($page->ID) . ')';
            echo '</span>';

            // 矢印ボタン
            echo '<div style="margin-left: 10px;">';
            echo '<button type="button" class="page-move-up button button-small"' . disabled($is_first, true, false) . ' style="margin-right: 5px;" data-page-id="' . esc_attr($page->ID) . '">&uarr;</button>';
            echo '<button type="button" class="page-move-down button button-small"' . disabled($is_last, true, false) . ' data-page-id="' . esc_attr($page->ID) . '">&darr;</button>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        $page_order_string = array();
        foreach ($ordered_pages as $page) {
            $page_order_string[] = $page->ID;
        }
        echo '<input type="hidden" name="page_order" id="page_order" value="' . esc_attr(implode(',', $page_order_string)) . '">';
        echo '</div>';
    } else {
        echo '<p>' . esc_html__('公開されている固定ページが見つかりませんでした。', 'llms-txt-generator-wp') . '</p>';
    }

    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="save_page_settings" class="button button-primary" value="' . esc_attr__('固定ページ設定を保存', 'llms-txt-generator-wp') . '">';
    echo '</p>';
    echo '</form>';

    echo '<hr>';

    echo '<h2>' . esc_html__('LLMS.txt生成', 'llms-txt-generator-wp') . '</h2>';
    echo '<p><strong>' . esc_html__('生成先:', 'llms-txt-generator-wp') . '</strong><br>';
    echo esc_html__('ファイルパス:', 'llms-txt-generator-wp') . ' <code>' . esc_html($file_path) . '</code><br>';
    echo esc_html__('アクセスURL:', 'llms-txt-generator-wp') . ' <a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($file_url) . '</a></p>';

    // 現在の設定表示
    echo '<p><strong>' . esc_html__('現在の設定:', 'llms-txt-generator-wp') . '</strong><br>';
    echo esc_html__('文字コード:', 'llms-txt-generator-wp') . ' <strong>' . ($current_encoding === 'SJIS' ? 'Shift-JIS' : 'UTF-8') . '</strong><br>';
    if (!empty($enabled_post_types)) {
        echo esc_html__('有効な投稿タイプ:', 'llms-txt-generator-wp') . ' <strong>' . esc_html(implode(', ', $enabled_post_types)) . '</strong><br>';
    } else {
        echo esc_html__('有効な投稿タイプ:', 'llms-txt-generator-wp') . ' <strong>' . esc_html__('なし', 'llms-txt-generator-wp') . '</strong><br>';
    }
    if (!empty($post_type_order)) {
        echo esc_html__('出力順序:', 'llms-txt-generator-wp') . ' <strong>' . esc_html(implode(' → ', $post_type_order)) . '</strong><br>';
    }
    if (!empty($enabled_pages)) {
        $enabled_page_titles = array();
        foreach ($enabled_pages as $page_id) {
            foreach ($available_pages as $page) {
                if ($page->ID == $page_id) {
                    $enabled_page_titles[] = $page->post_title;
                    break;
                }
            }
        }
        echo esc_html__('有効な固定ページ:', 'llms-txt-generator-wp') . ' <strong>' . esc_html(implode(', ', $enabled_page_titles)) . '</strong>';
    } else {
        echo esc_html__('有効な固定ページ:', 'llms-txt-generator-wp') . ' <strong>' . esc_html__('なし', 'llms-txt-generator-wp') . '</strong>';
    }

    if (!empty($page_order)) {
        // 出力される親ページのタイトルを順序設定に従って表示
        $output_page_titles = array();
        foreach ($page_order as $page_id) {
            // 有効な親ページのみ表示
            if (in_array($page_id, $enabled_pages)) {
                foreach ($available_pages as $page) {
                    if ($page->ID == $page_id && $page->post_parent == 0) {
                        $output_page_titles[] = $page->post_title;
                        break;
                    }
                }
            }
        }
        if (!empty($output_page_titles)) {
            echo '<br>' . esc_html__('親ページ出力順序:', 'llms-txt-generator-wp') . ' <strong>' . esc_html(implode(' → ', $output_page_titles)) . '</strong>';
        }
    }
    echo '</p>';

    if (file_exists($file_path)) {
        $last_modified = gmdate('Y-m-d H:i:s', filemtime($file_path));
        echo '<p><strong>' . esc_html__('現在のファイル状況:', 'llms-txt-generator-wp') . '</strong><br>';
        echo esc_html__('最終更新:', 'llms-txt-generator-wp') . ' ' . esc_html($last_modified) . '<br>';
        echo esc_html__('ファイルサイズ:', 'llms-txt-generator-wp') . ' ' . esc_html(size_format(filesize($file_path))) . '</p>';
    } else {
        echo '<p><strong>' . esc_html__('現在のファイル状況:', 'llms-txt-generator-wp') . '</strong> ' . esc_html__('ファイルはまだ生成されていません', 'llms-txt-generator-wp') . '</p>';
    }

    echo '<form method="post">';
    wp_nonce_field('llmstxtgen_generate_action', 'llmstxtgen_generate_nonce');
    echo '<input type="submit" name="generate_llms" class="button button-primary" value="' . esc_attr__('LLMS.txtを生成', 'llms-txt-generator-wp') . '">';
    echo '</form>';
    echo '</div>';
}
