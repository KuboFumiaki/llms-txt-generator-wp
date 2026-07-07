<?php
/**
 * LLMS.txt Generator Uninstall
 *
 * プラグインが削除された時に実行される処理
 *
 * @package LLMS_TXT_Generator
 * @since 1.0.0
 */

// WordPressからの正当な削除要求でない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// プラグインの設定データを削除
delete_option('llmstxtgen_custom_text');
delete_option('llmstxtgen_encoding');
delete_option('llmstxtgen_post_type_settings');
delete_option('llmstxtgen_page_settings');
delete_option('llmstxtgen_excerpt_length');

// 生成されたLLMS.txtファイルを削除
$llmstxtgen_file_path = ABSPATH . 'llms.txt';
if (file_exists($llmstxtgen_file_path)) {
    wp_delete_file($llmstxtgen_file_path);
}

// スケジュールされたイベントがあれば削除
wp_clear_scheduled_hook('llmstxtgen_cron');
