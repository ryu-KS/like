<?php

if (!defined('ABSPATH')) {
    exit;
}

class YesOrNo_Admin {
    /** @var YesOrNo_Data_Store */
    private $data_store;

    public function __construct($data_store) {
        $this->data_store = $data_store;
    }

    public function register() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('admin_post_yesorno_save_test_basic', array($this, 'handle_save_test_basic'));
        add_action('admin_post_yesorno_save_test_card', array($this, 'handle_save_test_card'));
        add_action('admin_post_yesorno_delete_test_card', array($this, 'handle_delete_test_card'));
        add_action('admin_post_yesorno_save_test_result', array($this, 'handle_save_test_result'));
        add_action('admin_post_yesorno_delete_test_result', array($this, 'handle_delete_test_result'));
        add_action('admin_post_yesorno_delete_test', array($this, 'handle_delete_test'));
        add_action('admin_post_yesorno_import_json', array($this, 'handle_import_json'));
        add_action('admin_post_yesorno_export_test_json', array($this, 'handle_export_test_json'));
        add_action('admin_post_yesorno_duplicate_test', array($this, 'handle_duplicate_test'));
        add_action('admin_post_yesorno_save_settings', array($this, 'handle_save_settings'));
    }

    public function register_menu() {
        add_menu_page('YesOrNo', 'YesOrNo', 'manage_options', 'yesorno', array($this, 'render_tests_page'), 'dashicons-format-status', 56);
        add_submenu_page('yesorno', 'Tests', 'Tests', 'manage_options', 'yesorno', array($this, 'render_tests_page'));
        add_submenu_page('yesorno', 'Settings', 'Settings', 'manage_options', 'yesorno-settings', array($this, 'render_settings_page'));
    }

    public function enqueue_assets($hook) {
        if (strpos((string) $hook, 'yesorno') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('yesorno-font', 'https://fonts.googleapis.com/earlyaccess/nanumgothic.css', array(), YESORNO_VERSION);
        wp_enqueue_style('yesorno-admin', YESORNO_URL . 'assets/css/admin.css', array('yesorno-font'), YESORNO_VERSION);
        wp_enqueue_script('yesorno-admin', YESORNO_URL . 'assets/js/admin.js', array('jquery'), YESORNO_VERSION, true);
    }

    public function render_tests_page() {
        $this->require_admin();

        $tests = $this->query_tests();
        $is_new = isset($_GET['new']) && absint($_GET['new']) === 1;
        $selected_id = isset($_GET['test']) ? absint($_GET['test']) : 0;
        $selected_test = null;

        if (!$is_new && $selected_id > 0) {
            $selected_test = $this->data_store->get_test($selected_id);
        }

        if (!$is_new && !$selected_test && !empty($tests)) {
            $selected_test = $tests[0];
            $selected_id = (int) $selected_test['post_id'];
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'basic';
        if (!in_array($tab, array('basic', 'cards', 'results'), true)) {
            $tab = 'basic';
        }

        $edit_card_id = isset($_GET['edit_card']) ? sanitize_title((string) wp_unslash($_GET['edit_card'])) : '';
        $edit_result_code = isset($_GET['edit_result']) ? sanitize_key((string) wp_unslash($_GET['edit_result'])) : '';

        ?>
        <div class="wrap taro-fortune-admin-wrap">
            <h1>YesOrNo Tests</h1>
            <?php $this->render_notice(); ?>

            <div class="taro-admin-layout">
                <aside class="taro-admin-sidebar taro-fortune-panel">
                    <h2>Test List</h2>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=yesorno&new=1')); ?>">New Test</a>

                    <div class="taro-test-list">
                        <?php if (empty($tests)) : ?>
                            <p>No tests yet.</p>
                        <?php else : ?>
                            <?php foreach ($tests as $item) : ?>
                                <a class="taro-test-link <?php echo ($selected_id === (int) $item['post_id'] && !$is_new) ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=yesorno&test=' . (int) $item['post_id'])); ?>">
                                    <strong><?php echo esc_html($item['title']); ?></strong>
                                    <span>[yesorno yesorno="<?php echo esc_html($item['slug_alias']); ?>"]</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </aside>

                <main class="taro-admin-main">
                    <?php $this->render_editor($selected_test, $tab, $is_new, $edit_card_id, $edit_result_code); ?>
                </main>
            </div>
        </div>
        <?php
    }

    private function query_tests() {
        $query = new WP_Query(array(
            'post_type' => 'yesorno_test',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'fields' => 'ids',
        ));

        $tests = array();
        foreach ((array) $query->posts as $post_id) {
            $test = $this->data_store->get_test((int) $post_id);
            if ($test) {
                $tests[] = $test;
            }
        }

        wp_reset_postdata();
        return $tests;
    }
    private function render_editor($test, $tab, $is_new, $edit_card_id = '', $edit_result_code = '') {
        $post_id = ($test && !$is_new) ? (int) $test['post_id'] : 0;
        $groups_quota = $test ? $test['groups_quota'] : array('A' => 2, 'B' => 2, 'C' => 2, 'D' => 2, 'E' => 2);
        $editing_card = $this->find_card_by_id($test, $edit_card_id);
        $editing_result = $this->find_result_by_code($test, $edit_result_code);
        $default_front_overlay_background = 'linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))';
        $default_back_overlay_background = 'linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))';
        $default_prism_background = 'linear-gradient(110deg, rgba(255, 255, 255, 0.05) 18%, rgba(255, 107, 129, 0.3) 32%, rgba(92, 245, 255, 0.26) 48%, rgba(255, 201, 107, 0.34) 64%, rgba(255, 255, 255, 0.05) 78%)';
        $prism_mix_blend_mode_options = array('screen', 'normal', 'multiply', 'overlay', 'soft-light', 'hard-light', 'color-dodge', 'lighten');
        $basic_prism_mix_blend_mode = ($test && isset($test['prism_mix_blend_mode'])) ? sanitize_key((string) $test['prism_mix_blend_mode']) : 'screen';
        if (!in_array($basic_prism_mix_blend_mode, $prism_mix_blend_mode_options, true)) {
            $basic_prism_mix_blend_mode = 'screen';
        }
        $card_prism_mix_blend_mode = ($editing_card && isset($editing_card['prism_mix_blend_mode'])) ? sanitize_key((string) $editing_card['prism_mix_blend_mode']) : '';
        if ($card_prism_mix_blend_mode !== '' && !in_array($card_prism_mix_blend_mode, $prism_mix_blend_mode_options, true)) {
            $card_prism_mix_blend_mode = '';
        }
        ?>
        <div class="taro-fortune-panel">
            <h2><?php echo $is_new ? 'Create Test' : 'Edit Test'; ?></h2>
            <?php if ($test && !$is_new) : ?>
                <p>
                    <code>[yesorno yesorno="<?php echo esc_html($test['slug_alias']); ?>"]</code>
                    <button type="button" class="button taro-fortune-copy-shortcode" data-shortcode="[yesorno yesorno=&quot;<?php echo esc_attr($test['slug_alias']); ?>&quot;]">Copy</button>
                </p>
                <div style="margin:8px 0;">
                    <form style="display:inline-block;margin-right:8px;" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="yesorno_export_test_json">
                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <?php wp_nonce_field('yesorno_export_test_json', 'yesorno_export_test_nonce'); ?>
                        <button type="submit" class="button">Export JSON</button>
                    </form>
                    <form style="display:inline-block;" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="yesorno_duplicate_test">
                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <?php wp_nonce_field('yesorno_duplicate_test', 'yesorno_duplicate_test_nonce'); ?>
                        <button type="submit" class="button">Duplicate Test</button>
                    </form>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this test?');">
                    <input type="hidden" name="action" value="yesorno_delete_test">
                    <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                    <?php wp_nonce_field('yesorno_delete_test', 'yesorno_delete_test_nonce'); ?>
                    <button type="submit" class="button button-link-delete">Delete Test</button>
                </form>
            <?php endif; ?>

            <nav class="taro-admin-tabs">
                <a href="#" class="taro-admin-tab <?php echo $tab === 'basic' ? 'is-active' : ''; ?>" data-tab="basic">Basic</a>
                <a href="#" class="taro-admin-tab <?php echo $tab === 'cards' ? 'is-active' : ''; ?>" data-tab="cards">Cards</a>
                <a href="#" class="taro-admin-tab <?php echo $tab === 'results' ? 'is-active' : ''; ?>" data-tab="results">Results</a>
            </nav>

            <section class="taro-tab-panel <?php echo $tab === 'basic' ? 'is-active' : ''; ?>" data-tab-panel="basic">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="yesorno_save_test_basic">
                    <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                    <?php wp_nonce_field('yesorno_save_test_basic', 'yesorno_basic_nonce'); ?>

                    <label>Title</label>
                    <input type="text" class="regular-text" name="title" required value="<?php echo esc_attr($test ? $test['title'] : ''); ?>">

                    <label>Slug Alias</label>
                    <input type="text" class="regular-text" name="slug_alias" value="<?php echo esc_attr($test ? $test['slug_alias'] : ''); ?>">

                    <label>Description</label>
                    <textarea class="large-text" rows="3" name="description"><?php echo esc_textarea($test ? $test['description'] : ''); ?></textarea>

                    <label>Card Back Image URL</label>
                    <div class="taro-fortune-media-row">
                        <input id="yesorno-back-image" type="url" class="regular-text" name="card_back_image_url" value="<?php echo esc_attr($test ? $test['card_back_image_url'] : ''); ?>">
                        <button type="button" class="button taro-fortune-pick-media" data-target="#yesorno-back-image">Pick Media</button>
                    </div>
                    <label>Start Card Image URL</label>
                    <div class="taro-fortune-media-row">
                        <input id="yesorno-start-image" type="url" class="regular-text" name="start_image_url" value="<?php echo esc_attr($test && !empty($test['start_image_url']) ? $test['start_image_url'] : ''); ?>">
                        <button type="button" class="button taro-fortune-pick-media" data-target="#yesorno-start-image">Pick Media</button>
                    </div>

                    <div class="taro-inline-grid">
                        <div>
                            <label>Display Count (8~10)</label>
                            <input type="number" min="8" max="10" class="small-text" name="display_count" value="<?php echo esc_attr((string) ($test ? $test['display_count'] : 8)); ?>">
                        </div>
                        <div>
                            <label>Pool Count (16~24)</label>
                            <input type="number" min="16" max="24" class="small-text" name="pool_count" value="<?php echo esc_attr((string) ($test ? $test['pool_count'] : 16)); ?>">
                        </div>
                    </div>

                    <label>Groups Quota JSON</label>
                    <textarea class="large-text" rows="3" name="groups_quota_json"><?php echo esc_textarea(wp_json_encode($groups_quota, JSON_UNESCAPED_UNICODE)); ?></textarea>

                    <label>Front Overlay Background</label>
                    <textarea
                        class="large-text code"
                        rows="2"
                        name="front_overlay_background"
                        placeholder="<?php echo esc_attr($default_front_overlay_background); ?>"
                    ><?php echo esc_textarea($test && isset($test['front_overlay_background']) ? $test['front_overlay_background'] : $default_front_overlay_background); ?></textarea>

                    <label>Back Overlay Background</label>
                    <textarea
                        class="large-text code"
                        rows="2"
                        name="back_overlay_background"
                        placeholder="<?php echo esc_attr($default_back_overlay_background); ?>"
                    ><?php echo esc_textarea($test && isset($test['back_overlay_background']) ? $test['back_overlay_background'] : $default_back_overlay_background); ?></textarea>

                    <label>Prism Background</label>
                    <textarea
                        class="large-text code"
                        rows="3"
                        name="prism_background"
                        placeholder="<?php echo esc_attr($default_prism_background); ?>"
                    ><?php echo esc_textarea($test && isset($test['prism_background']) ? $test['prism_background'] : $default_prism_background); ?></textarea>

                    <label>Prism Mix Blend Mode</label>
                    <select class="regular-text" name="prism_mix_blend_mode">
                        <?php foreach ($prism_mix_blend_mode_options as $mode) : ?>
                            <option value="<?php echo esc_attr($mode); ?>" <?php selected($basic_prism_mix_blend_mode, $mode); ?>><?php echo esc_html($mode); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Default: <code>screen</code></p>

                    <label><input type="checkbox" name="active" value="1" <?php checked($test ? !empty($test['active']) : true); ?>> Active Test</label>

                    <p class="submit"><button class="button button-primary" type="submit">Save Basic</button></p>
                </form>

                <?php if ($test && !$is_new) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this test?');">
                        <input type="hidden" name="action" value="yesorno_delete_test">
                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <?php wp_nonce_field('yesorno_delete_test', 'yesorno_delete_test_nonce'); ?>
                        <button type="submit" class="button button-link-delete">Delete Test</button>
                    </form>
                <?php endif; ?>

                <hr>
                <h3>JSON Bulk Import (full replace: basic+cards+results)</h3>
                <p>JSON format: <code>{"basic":{},"cards":[],"results":[]}</code></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="yesorno_import_json">
                    <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                    <?php wp_nonce_field('yesorno_import_json', 'yesorno_import_nonce'); ?>
                    <textarea class="large-text code" rows="10" name="import_json" placeholder='{"basic":{"title":"Love Type Test","slug_alias":"love-type","display_count":10,"pool_count":20,"groups_quota":{"A":2,"B":2,"C":2,"D":2,"E":2},"active":1},"cards":[],"results":[]}'></textarea>
                    <p class="submit"><button class="button" type="submit">Import JSON</button></p>
                </form>
            </section>

            <section class="taro-tab-panel <?php echo $tab === 'cards' ? 'is-active' : ''; ?>" data-tab-panel="cards">
                <?php if (!$test || $is_new) : ?>
                    <p>Save basic info first.</p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="yesorno_save_test_card">
                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <input type="hidden" name="card_id" value="<?php echo esc_attr($editing_card ? $editing_card['id'] : ''); ?>">
                        <?php wp_nonce_field('yesorno_save_test_card', 'yesorno_card_nonce'); ?>

                        <label>Card Image URL</label>
                        <div class="taro-fortune-media-row">
                            <input id="yesorno-card-image-url" type="url" class="regular-text" name="image_url" value="<?php echo esc_attr($editing_card ? $editing_card['image_url'] : ''); ?>">
                            <button type="button" class="button taro-fortune-pick-media" data-target="#yesorno-card-image-url">Pick Media</button>
                        </div>

                        <label>Question Text</label>
                        <input type="text" class="regular-text" name="question_text" required value="<?php echo esc_attr($editing_card ? $editing_card['question_text'] : ''); ?>">

                        <label>Sub Text</label>
                        <textarea class="large-text" rows="2" name="sub_text"><?php echo esc_textarea($editing_card ? $editing_card['sub_text'] : ''); ?></textarea>

                        <div class="taro-inline-grid">
                            <div>
                                <label>Group</label>
                                <input type="text" class="regular-text" name="group" required value="<?php echo esc_attr($editing_card ? $editing_card['group'] : ''); ?>">
                            </div>
                            <div>
                                <label><input type="checkbox" name="active" value="1" <?php checked($editing_card ? !empty($editing_card['active']) : true); ?>> Active</label>
                            </div>
                        </div>

                        <label>score_yes JSON</label>
                        <textarea class="large-text" rows="2" name="score_yes_json"><?php echo esc_textarea($editing_card ? wp_json_encode($editing_card['score_yes'], JSON_UNESCAPED_UNICODE) : '{"default":1}'); ?></textarea>
                        <label>score_no JSON</label>
                        <textarea class="large-text" rows="2" name="score_no_json"><?php echo esc_textarea($editing_card ? wp_json_encode($editing_card['score_no'], JSON_UNESCAPED_UNICODE) : '{"default":0}'); ?></textarea>

                        <label>Front Overlay Background</label>
                        <textarea
                            class="large-text code"
                            rows="2"
                            name="front_overlay_background"
                            placeholder="비워두면 Basic 설정값 사용"
                        ><?php echo esc_textarea($editing_card && isset($editing_card['front_overlay_background']) ? $editing_card['front_overlay_background'] : ''); ?></textarea>

                        <label>Back Overlay Background</label>
                        <textarea
                            class="large-text code"
                            rows="2"
                            name="back_overlay_background"
                            placeholder="비워두면 Basic 설정값 사용"
                        ><?php echo esc_textarea($editing_card && isset($editing_card['back_overlay_background']) ? $editing_card['back_overlay_background'] : ''); ?></textarea>

                        <label>Prism Background</label>
                        <textarea
                            class="large-text code"
                            rows="3"
                            name="prism_background"
                            placeholder="비워두면 Basic 설정값 사용"
                        ><?php echo esc_textarea($editing_card && isset($editing_card['prism_background']) ? $editing_card['prism_background'] : ''); ?></textarea>
                        <label>Prism Mix Blend Mode</label>
                        <select class="regular-text" name="prism_mix_blend_mode">
                            <option value="" <?php selected($card_prism_mix_blend_mode, ''); ?>>Basic 설정 사용</option>
                            <?php foreach ($prism_mix_blend_mode_options as $mode) : ?>
                                <option value="<?php echo esc_attr($mode); ?>" <?php selected($card_prism_mix_blend_mode, $mode); ?>><?php echo esc_html($mode); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">비워두면 Basic 설정값(Overlay/Prism)을 상속합니다.</p>

                        <p class="submit"><button class="button button-primary" type="submit"><?php echo $editing_card ? 'Update Card' : 'Save Card'; ?></button></p>
                    </form>

                    <hr>
                    <h3>Registered Cards</h3>
                    <div class="taro-fortune-card-list">
                        <?php if (empty($test['cards'])) : ?>
                            <p>No cards registered.</p>
                        <?php else : ?>
                            <?php foreach ($test['cards'] as $card) : ?>
                                <article class="taro-fortune-card-item">
                                    <?php if (!empty($card['image_url'])) : ?>
                                        <img src="<?php echo esc_url($card['image_url']); ?>" alt="<?php echo esc_attr($card['question_text']); ?>">
                                    <?php else : ?>
                                        <div style="width:120px;height:80px;border:1px dashed #c3c4c7;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#646970;font-size:12px;margin-bottom:8px;">No Image</div>
                                    <?php endif; ?>
                                    <h4><?php echo esc_html($card['question_text']); ?></h4>
                                    <p>Group: <?php echo esc_html($card['group']); ?> | Active: <?php echo !empty($card['active']) ? 'Y' : 'N'; ?></p>
                                    <p>
                                        <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'yesorno', 'test' => $post_id, 'tab' => 'cards', 'edit_card' => $card['id']), admin_url('admin.php'))); ?>">Edit</a>
                                    </p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this card?');">
                                        <input type="hidden" name="action" value="yesorno_delete_test_card">
                                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                                        <input type="hidden" name="card_id" value="<?php echo esc_attr($card['id']); ?>">
                                        <?php wp_nonce_field('yesorno_delete_test_card', 'yesorno_delete_card_nonce'); ?>
                                        <button type="submit" class="button button-link-delete">Delete</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section class="taro-tab-panel <?php echo $tab === 'results' ? 'is-active' : ''; ?>" data-tab-panel="results">
                <?php if (!$test || $is_new) : ?>
                    <p>Save basic info first.</p>
                <?php else : ?>
                    <p><strong>Validation:</strong> <?php echo esc_html($this->summarize_result_health($test)); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="yesorno_save_test_result">
                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <input type="hidden" name="result_code_prev" value="<?php echo esc_attr($editing_result ? $editing_result['result_code'] : ''); ?>">
                        <?php wp_nonce_field('yesorno_save_test_result', 'yesorno_result_nonce'); ?>

                        <label>Result Code</label>
                        <input type="text" class="regular-text" name="result_code" required value="<?php echo esc_attr($editing_result ? $editing_result['result_code'] : ''); ?>">

                        <label>Label</label>
                        <input type="text" class="regular-text" name="label" required value="<?php echo esc_attr($editing_result ? $editing_result['label'] : ''); ?>">

                        <label>Linked Post/Page</label>
                        <select id="yesorno-result-post" class="regular-text" name="post_id">
                            <option value="0">Select post or page</option>
                            <?php
                            $posts = get_posts(array(
                                'post_type' => array('post', 'page'),
                                'post_status' => 'publish',
                                'posts_per_page' => 200,
                            ));
                            foreach ($posts as $p) {
                                $url = get_permalink($p->ID);
                                echo '<option value="' . esc_attr((string) $p->ID) . '" data-url="' . esc_attr((string) $url) . '" ' . selected($editing_result ? (int) $editing_result['post_id'] : 0, (int) $p->ID, false) . '>' . esc_html($p->post_title) . '</option>';
                            }
                            ?>
                        </select>

                        <label>Result URL (auto)</label>
                        <input id="yesorno-result-url" type="url" class="regular-text" name="result_url" readonly value="<?php echo esc_attr($editing_result ? $editing_result['result_url'] : ''); ?>">
                        <label>Result Image URL</label>
                        <div class="taro-fortune-media-row">
                            <input id="yesorno-result-image-url" type="url" class="regular-text" name="result_image_url" value="<?php echo esc_attr($editing_result && !empty($editing_result['result_image_url']) ? $editing_result['result_image_url'] : ''); ?>">
                            <button type="button" class="button taro-fortune-pick-media" data-target="#yesorno-result-image-url">Pick Media</button>
                        </div>
                        <label>Result Summary</label>
                        <textarea class="large-text" rows="2" name="result_summary"><?php echo esc_textarea($editing_result && !empty($editing_result['result_summary']) ? $editing_result['result_summary'] : ''); ?></textarea>
                        <label>Result CTA Label</label>
                        <input type="text" class="regular-text" name="result_cta_label" value="<?php echo esc_attr($editing_result && !empty($editing_result['result_cta_label']) ? $editing_result['result_cta_label'] : 'View Details'); ?>">
                        <label><input type="checkbox" name="active" value="1" <?php checked($editing_result ? !empty($editing_result['active']) : true); ?>> Active</label>

                        <p class="submit"><button class="button button-primary" type="submit"><?php echo $editing_result ? 'Update Result' : 'Save Result'; ?></button></p>
                    </form>

                    <hr>
                    <h3>Result List</h3>
                    <div class="taro-fortune-card-list">
                        <?php if (empty($test['results'])) : ?>
                            <p>No results registered.</p>
                        <?php else : ?>
                            <?php foreach ($test['results'] as $result) : ?>
                                <article class="taro-fortune-card-item">
                                    <?php if (!empty($result['result_image_url'])) : ?>
                                        <img src="<?php echo esc_url($result['result_image_url']); ?>" alt="<?php echo esc_attr($result['label']); ?>">
                                    <?php endif; ?>
                                    <h4><?php echo esc_html($result['label']); ?> (<?php echo esc_html($result['result_code']); ?>)</h4>
                                    <p>Active: <?php echo !empty($result['active']) ? 'Y' : 'N'; ?></p>
                                    <p><?php echo esc_html(!empty($result['result_summary']) ? $result['result_summary'] : ''); ?></p>
                                    <p><?php echo !empty($result['result_url']) ? esc_html($result['result_url']) : 'No URL'; ?></p>
                                    <p>
                                        <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'yesorno', 'test' => $post_id, 'tab' => 'results', 'edit_result' => $result['result_code']), admin_url('admin.php'))); ?>">Edit</a>
                                    </p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this result?');">
                                        <input type="hidden" name="action" value="yesorno_delete_test_result">
                                        <input type="hidden" name="test_id" value="<?php echo esc_attr((string) $post_id); ?>">
                                        <input type="hidden" name="result_code" value="<?php echo esc_attr($result['result_code']); ?>">
                                        <?php wp_nonce_field('yesorno_delete_test_result', 'yesorno_delete_result_nonce'); ?>
                                        <button type="submit" class="button button-link-delete">Delete</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public function render_settings_page() {
        $this->require_admin();
        $settings = $this->data_store->get_settings();
        ?>
        <div class="wrap taro-fortune-admin-wrap">
            <h1>Settings</h1>
            <?php $this->render_notice(); ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="taro-fortune-panel">
                <input type="hidden" name="action" value="yesorno_save_settings">
                <?php wp_nonce_field('yesorno_save_settings', 'yesorno_settings_nonce'); ?>
                <label>Default Card Back URL</label>
                <input type="url" class="regular-text" name="default_card_back_image_url" value="<?php echo esc_attr($settings['default_card_back_image_url']); ?>">
                <label>Copyright</label>
                <input type="text" class="regular-text" name="copyright" value="<?php echo esc_attr(html_entity_decode($settings['copyright'], ENT_QUOTES, 'UTF-8')); ?>">
                <p class="submit"><button class="button button-primary" type="submit">Save</button></p>
            </form>
        </div>
        <?php
    }

    public function handle_save_test_basic() {
        $this->require_admin();
        check_admin_referer('yesorno_save_test_basic', 'yesorno_basic_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $existing = $test_id > 0 ? $this->data_store->get_test($test_id) : null;

        $groups = json_decode((string) (isset($_POST['groups_quota_json']) ? wp_unslash($_POST['groups_quota_json']) : '{}'), true);
        if (!is_array($groups)) {
            $groups = array('A' => 2, 'B' => 2, 'C' => 2, 'D' => 2, 'E' => 2);
        }

        $payload = array(
            'id' => $existing ? $existing['id'] : '',
            'slug_alias' => isset($_POST['slug_alias']) ? sanitize_key(wp_unslash($_POST['slug_alias'])) : '',
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'card_back_image_url' => isset($_POST['card_back_image_url']) ? esc_url_raw(wp_unslash($_POST['card_back_image_url'])) : '',
            'start_image_url' => isset($_POST['start_image_url']) ? esc_url_raw(wp_unslash($_POST['start_image_url'])) : '',
            'front_overlay_background' => isset($_POST['front_overlay_background']) ? wp_unslash($_POST['front_overlay_background']) : '',
            'back_overlay_background' => isset($_POST['back_overlay_background']) ? wp_unslash($_POST['back_overlay_background']) : '',
            'prism_background' => isset($_POST['prism_background']) ? wp_unslash($_POST['prism_background']) : '',
            'prism_mix_blend_mode' => isset($_POST['prism_mix_blend_mode']) ? sanitize_key(wp_unslash($_POST['prism_mix_blend_mode'])) : '',
            'display_count' => isset($_POST['display_count']) ? absint($_POST['display_count']) : 8,
            'pool_count' => isset($_POST['pool_count']) ? absint($_POST['pool_count']) : 16,
            'groups_quota' => $groups,
            'active' => isset($_POST['active']) ? 1 : 0,
            'cards' => $existing ? $existing['cards'] : array(),
            'results' => $existing ? $existing['results'] : array(),
        );

        $saved_id = $this->data_store->save_test($payload, $test_id > 0 ? $test_id : 0);
        if (!$saved_id) {
            $this->redirect_with_message('yesorno', 'Failed to save test.');
        }

        $this->redirect('yesorno', array(
            'test' => $saved_id,
            'tab' => 'basic',
            'message' => rawurlencode('Basic saved.'),
        ));
    }

    public function handle_save_test_card() {
        $this->require_admin();
        check_admin_referer('yesorno_save_test_card', 'yesorno_card_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $test = $this->data_store->get_test($test_id);
        if (!$test) {
            $this->redirect_with_message('yesorno', 'Test not found.');
        }

        $score_yes = json_decode((string) (isset($_POST['score_yes_json']) ? wp_unslash($_POST['score_yes_json']) : '{}'), true);
        $score_no = json_decode((string) (isset($_POST['score_no_json']) ? wp_unslash($_POST['score_no_json']) : '{}'), true);
        if (!is_array($score_yes)) {
            $score_yes = array();
        }
        if (!is_array($score_no)) {
            $score_no = array();
        }

        $incoming = array(
            'id' => isset($_POST['card_id']) ? sanitize_title(wp_unslash($_POST['card_id'])) : '',
            'image_url' => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
            'title' => '',
            'question_text' => isset($_POST['question_text']) ? sanitize_text_field(wp_unslash($_POST['question_text'])) : '',
            'sub_text' => isset($_POST['sub_text']) ? sanitize_textarea_field(wp_unslash($_POST['sub_text'])) : '',
            'group' => isset($_POST['group']) ? sanitize_text_field(wp_unslash($_POST['group'])) : '',
            'front_overlay_background' => isset($_POST['front_overlay_background']) ? wp_unslash($_POST['front_overlay_background']) : '',
            'back_overlay_background' => isset($_POST['back_overlay_background']) ? wp_unslash($_POST['back_overlay_background']) : '',
            'prism_background' => isset($_POST['prism_background']) ? wp_unslash($_POST['prism_background']) : '',
            'prism_mix_blend_mode' => isset($_POST['prism_mix_blend_mode']) ? sanitize_key(wp_unslash($_POST['prism_mix_blend_mode'])) : '',
            'score_yes' => $score_yes,
            'score_no' => $score_no,
            'active' => isset($_POST['active']) ? 1 : 0,
        );
        $updated = false;
        if ($incoming['id'] !== '') {
            foreach ($test['cards'] as $index => $card) {
                if ($card['id'] === $incoming['id']) {
                    $test['cards'][$index] = array_merge($card, $incoming);
                    $updated = true;
                    break;
                }
            }
        }

        if (!$updated) {
            $incoming['id'] = sanitize_title('card-' . wp_generate_password(8, false, false));
            $test['cards'][] = $incoming;
        }

        $this->data_store->save_test($test, $test_id);
        $this->redirect('yesorno', array(
            'test' => $test_id,
            'tab' => 'cards',
            'message' => rawurlencode($updated ? 'Card updated.' : 'Card saved.'),
        ));
    }

    public function handle_delete_test_card() {
        $this->require_admin();
        check_admin_referer('yesorno_delete_test_card', 'yesorno_delete_card_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $card_id = isset($_POST['card_id']) ? sanitize_title(wp_unslash($_POST['card_id'])) : '';
        $test = $this->data_store->get_test($test_id);
        if ($test) {
            $test['cards'] = array_values(array_filter($test['cards'], function ($card) use ($card_id) {
                return $card['id'] !== $card_id;
            }));
            $this->data_store->save_test($test, $test_id);
        }
        $this->redirect('yesorno', array('test' => $test_id, 'tab' => 'cards', 'message' => rawurlencode('Card deleted.')));
    }

    public function handle_save_test_result() {
        $this->require_admin();
        check_admin_referer('yesorno_save_test_result', 'yesorno_result_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $test = $this->data_store->get_test($test_id);
        if (!$test) {
            $this->redirect_with_message('yesorno', 'Test not found.');
        }

        $result_code = isset($_POST['result_code']) ? sanitize_key(wp_unslash($_POST['result_code'])) : '';
        $prev_code = isset($_POST['result_code_prev']) ? sanitize_key(wp_unslash($_POST['result_code_prev'])) : '';
        if ($result_code === '') {
            $this->redirect('yesorno', array('test' => $test_id, 'tab' => 'results', 'message' => rawurlencode('Result code required.')));
        }

        foreach ($test['results'] as $item) {
            if ($item['result_code'] === $result_code && $item['result_code'] !== $prev_code) {
                $this->redirect('yesorno', array('test' => $test_id, 'tab' => 'results', 'message' => rawurlencode('Duplicate result code.')));
            }
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $cta_label = isset($_POST['result_cta_label']) ? sanitize_text_field(wp_unslash($_POST['result_cta_label'])) : '';
        if ($cta_label === '') {
            $cta_label = 'View Details';
        }
        $incoming = array(
            'result_code' => $result_code,
            'label' => isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '',
            'post_id' => $post_id,
            'result_url' => $post_id > 0 ? get_permalink($post_id) : '',
            'result_image_url' => isset($_POST['result_image_url']) ? esc_url_raw(wp_unslash($_POST['result_image_url'])) : '',
            'result_summary' => isset($_POST['result_summary']) ? sanitize_textarea_field(wp_unslash($_POST['result_summary'])) : '',
            'result_cta_label' => $cta_label,
            'active' => isset($_POST['active']) ? 1 : 0,
        );

        $updated = false;
        if ($prev_code !== '') {
            foreach ($test['results'] as $index => $result) {
                if ($result['result_code'] === $prev_code) {
                    $test['results'][$index] = array_merge($result, $incoming);
                    $updated = true;
                    break;
                }
            }
        }

        if (!$updated) {
            $test['results'][] = $incoming;
        }

        $this->data_store->save_test($test, $test_id);
        $this->redirect('yesorno', array(
            'test' => $test_id,
            'tab' => 'results',
            'message' => rawurlencode($updated ? 'Result updated.' : 'Result saved.'),
        ));
    }

    public function handle_delete_test_result() {
        $this->require_admin();
        check_admin_referer('yesorno_delete_test_result', 'yesorno_delete_result_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $result_code = isset($_POST['result_code']) ? sanitize_key(wp_unslash($_POST['result_code'])) : '';
        $test = $this->data_store->get_test($test_id);
        if ($test) {
            $test['results'] = array_values(array_filter($test['results'], function ($result) use ($result_code) {
                return $result['result_code'] !== $result_code;
            }));
            $this->data_store->save_test($test, $test_id);
        }
        $this->redirect('yesorno', array('test' => $test_id, 'tab' => 'results', 'message' => rawurlencode('Result deleted.')));
    }

    public function handle_delete_test() {
        $this->require_admin();
        check_admin_referer('yesorno_delete_test', 'yesorno_delete_test_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        if ($test_id > 0) {
            $this->data_store->delete_test($test_id);
        }

        $this->redirect_with_message('yesorno', 'Test deleted.');
    }

    public function handle_import_json() {
        $this->require_admin();
        check_admin_referer('yesorno_import_json', 'yesorno_import_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $parsed = $this->data_store->parse_json_import(isset($_POST['import_json']) ? wp_unslash($_POST['import_json']) : '');
        if (is_wp_error($parsed)) {
            $this->redirect('yesorno', array('test' => $test_id, 'tab' => 'basic', 'message' => rawurlencode($parsed->get_error_message())));
        }

        $saved = $this->data_store->save_test(array(
            'id' => $parsed['basic']['id'],
            'slug_alias' => $parsed['basic']['slug_alias'],
            'title' => $parsed['basic']['title'],
            'description' => $parsed['basic']['description'],
            'card_back_image_url' => $parsed['basic']['card_back_image_url'],
            'start_image_url' => isset($parsed['basic']['start_image_url']) ? $parsed['basic']['start_image_url'] : '',
            'front_overlay_background' => isset($parsed['basic']['front_overlay_background']) ? $parsed['basic']['front_overlay_background'] : '',
            'back_overlay_background' => isset($parsed['basic']['back_overlay_background']) ? $parsed['basic']['back_overlay_background'] : '',
            'prism_background' => isset($parsed['basic']['prism_background']) ? $parsed['basic']['prism_background'] : '',
            'prism_mix_blend_mode' => isset($parsed['basic']['prism_mix_blend_mode']) ? $parsed['basic']['prism_mix_blend_mode'] : '',
            'display_count' => $parsed['basic']['display_count'],
            'pool_count' => $parsed['basic']['pool_count'],
            'groups_quota' => $parsed['basic']['groups_quota'],
            'active' => $parsed['basic']['active'],
            'cards' => $parsed['cards'],
            'results' => $parsed['results'],
        ), $test_id);

        $this->redirect('yesorno', array('test' => $saved, 'tab' => 'basic', 'message' => rawurlencode('JSON imported.')));
    }

    public function handle_export_test_json() {
        $this->require_admin();
        check_admin_referer('yesorno_export_test_json', 'yesorno_export_test_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $test = $this->data_store->get_test($test_id);
        if (!$test) {
            $this->redirect_with_message('yesorno', 'Test not found.');
        }

        $payload = array(
            'basic' => array(
                'id' => $test['id'],
                'slug_alias' => $test['slug_alias'],
                'title' => $test['title'],
                'description' => $test['description'],
                'card_back_image_url' => $test['card_back_image_url'],
                'start_image_url' => isset($test['start_image_url']) ? $test['start_image_url'] : '',
                'front_overlay_background' => isset($test['front_overlay_background']) ? $test['front_overlay_background'] : '',
                'back_overlay_background' => isset($test['back_overlay_background']) ? $test['back_overlay_background'] : '',
                'prism_background' => isset($test['prism_background']) ? $test['prism_background'] : '',
                'prism_mix_blend_mode' => isset($test['prism_mix_blend_mode']) ? $test['prism_mix_blend_mode'] : '',
                'display_count' => $test['display_count'],
                'pool_count' => $test['pool_count'],
                'groups_quota' => $test['groups_quota'],
                'active' => $test['active'],
            ),
            'cards' => isset($test['cards']) && is_array($test['cards']) ? $test['cards'] : array(),
            'results' => isset($test['results']) && is_array($test['results']) ? $test['results'] : array(),
        );

        $filename = sanitize_file_name(($test['slug_alias'] ? $test['slug_alias'] : ('test-' . $test_id)) . '.json');
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=' . $filename);
        echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public function handle_duplicate_test() {
        $this->require_admin();
        check_admin_referer('yesorno_duplicate_test', 'yesorno_duplicate_test_nonce');

        $test_id = isset($_POST['test_id']) ? absint($_POST['test_id']) : 0;
        $source = $this->data_store->get_test($test_id);
        if (!$source) {
            $this->redirect_with_message('yesorno', 'Test not found.');
        }

        $new_title = sanitize_text_field((string) $source['title']) . ' (Copy)';
        $base_slug = $this->data_store->generate_slug_alias($new_title);
        $new_slug_alias = $this->make_unique_slug_alias($base_slug);

        $payload = array(
            'id' => '',
            'slug_alias' => $new_slug_alias,
            'title' => $new_title,
            'description' => isset($source['description']) ? $source['description'] : '',
            'card_back_image_url' => isset($source['card_back_image_url']) ? $source['card_back_image_url'] : '',
            'start_image_url' => isset($source['start_image_url']) ? $source['start_image_url'] : '',
            'front_overlay_background' => isset($source['front_overlay_background']) ? $source['front_overlay_background'] : '',
            'back_overlay_background' => isset($source['back_overlay_background']) ? $source['back_overlay_background'] : '',
            'prism_background' => isset($source['prism_background']) ? $source['prism_background'] : '',
            'prism_mix_blend_mode' => isset($source['prism_mix_blend_mode']) ? $source['prism_mix_blend_mode'] : '',
            'display_count' => isset($source['display_count']) ? $source['display_count'] : 8,
            'pool_count' => isset($source['pool_count']) ? $source['pool_count'] : 16,
            'groups_quota' => isset($source['groups_quota']) ? $source['groups_quota'] : array(),
            'active' => 0,
            'cards' => isset($source['cards']) ? $source['cards'] : array(),
            'results' => isset($source['results']) ? $source['results'] : array(),
        );

        $saved = $this->data_store->save_test($payload, 0);
        if (!$saved) {
            $this->redirect_with_message('yesorno', 'Failed to duplicate test.');
        }

        $this->redirect('yesorno', array(
            'test' => $saved,
            'tab' => 'basic',
            'message' => rawurlencode('Test duplicated.'),
        ));
    }

    public function handle_save_settings() {
        $this->require_admin();
        check_admin_referer('yesorno_save_settings', 'yesorno_settings_nonce');

        $settings = $this->data_store->get_settings();
        $settings['default_card_back_image_url'] = isset($_POST['default_card_back_image_url']) ? esc_url_raw(wp_unslash($_POST['default_card_back_image_url'])) : '';
        $settings['copyright'] = isset($_POST['copyright']) ? sanitize_text_field(wp_unslash($_POST['copyright'])) : '';

        $this->data_store->save_settings($settings);
        $this->redirect_with_message('yesorno-settings', 'Settings saved.');
    }

    private function summarize_result_health($test) {
        if (!$test) {
            return 'No test selected';
        }

        $total = count($test['results']);
        $active = 0;
        $missing_url = 0;
        foreach ($test['results'] as $result) {
            if (!empty($result['active'])) {
                $active++;
            }
            if (empty($result['result_url'])) {
                $missing_url++;
            }
        }

        return sprintf('total=%d, active=%d, missing_url=%d', $total, $active, $missing_url);
    }

    private function find_card_by_id($test, $card_id) {
        if (!$test || empty($card_id) || empty($test['cards']) || !is_array($test['cards'])) {
            return null;
        }
        foreach ($test['cards'] as $card) {
            if (!empty($card['id']) && $card['id'] === $card_id) {
                return $card;
            }
        }
        return null;
    }

    private function find_result_by_code($test, $result_code) {
        if (!$test || empty($result_code) || empty($test['results']) || !is_array($test['results'])) {
            return null;
        }
        foreach ($test['results'] as $result) {
            if (!empty($result['result_code']) && $result['result_code'] === $result_code) {
                return $result;
            }
        }
        return null;
    }

    private function render_notice() {
        if (!isset($_GET['message'])) {
            return;
        }
        $message = sanitize_text_field(urldecode(wp_unslash($_GET['message'])));
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'yesorno'));
        }
    }

    private function redirect_with_message($page, $message) {
        $this->redirect($page, array('message' => rawurlencode($message)));
    }

    private function make_unique_slug_alias($base_slug) {
        $base = sanitize_key((string) $base_slug);
        if ($base === '') {
            $base = 'yesorno-' . wp_generate_password(6, false, false);
        }

        $existing = array();
        foreach ($this->data_store->get_tests() as $test) {
            if (!empty($test['slug_alias'])) {
                $existing[sanitize_key((string) $test['slug_alias'])] = true;
            }
        }

        if (!isset($existing[$base])) {
            return $base;
        }

        $suffix = 2;
        while (isset($existing[$base . '-' . $suffix])) {
            $suffix++;
        }

        return $base . '-' . $suffix;
    }

    private function redirect($page, $args = array()) {
        $query = array_merge(array('page' => $page), is_array($args) ? $args : array());
        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }
}

