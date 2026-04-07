<?php

if (!defined('ABSPATH')) {
    exit;
}

class YesOrNo_Frontend {
    /** @var YesOrNo_Data_Store */
    private $data_store;

    public function __construct($data_store) {
        $this->data_store = $data_store;
    }

    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_post_nopriv_yesorno_bootstrap', array($this, 'handle_public_bootstrap'));
        add_action('admin_post_yesorno_bootstrap', array($this, 'handle_public_bootstrap'));
        add_action('admin_post_nopriv_yesorno_result', array($this, 'handle_public_result'));
        add_action('admin_post_yesorno_result', array($this, 'handle_public_result'));
        add_shortcode('yesorno', array($this, 'render_shortcode'));
        add_shortcode('taro_fortune', array($this, 'render_legacy_shortcode'));
    }

    public function register_assets() {
        wp_register_style('yesorno-font', 'https://fonts.googleapis.com/earlyaccess/nanumgothic.css', array(), YESORNO_VERSION);
        wp_register_style('yesorno-frontend', YESORNO_URL . 'assets/css/frontend.css', array('yesorno-font'), YESORNO_VERSION);
        wp_register_script('yesorno-gsap', 'https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/gsap.min.js', array(), '3.12.7', true);
        wp_register_script('yesorno-frontend', YESORNO_URL . 'assets/js/frontend.js', array('yesorno-gsap'), YESORNO_VERSION, true);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('yesorno' => '', 'id' => ''), $atts, 'yesorno');
        $lookup = sanitize_key((string) $atts['yesorno']);
        if ($lookup === '') {
            $lookup = sanitize_text_field((string) $atts['id']);
        }

        $test = null;
        if ($lookup !== '') {
            $test = $this->data_store->get_test_by_slug_or_id($lookup);
        }
        if (!$test) {
            $test = $this->data_store->get_default_test();
        }
        if (!$test) {
            return '';
        }

        return $this->render_test($test);
    }

    public function render_legacy_shortcode($atts) {
        return $this->render_shortcode($atts);
    }

    private function render_test($test) {
        $settings = $this->data_store->get_settings();

        wp_enqueue_style('yesorno-frontend');
        wp_enqueue_script('yesorno-frontend');

        // [추가됨] 커스텀 CSS가 존재할 경우 인라인으로 직접 주입
        if (!empty($test['custom_css'])) {
            wp_add_inline_style('yesorno-frontend', $test['custom_css']);
        }

        wp_localize_script('yesorno-frontend', 'YesOrNoConfig', array(
            'settings' => array(
                'default_card_back_image_url' => !empty($settings['default_card_back_image_url']) ? $settings['default_card_back_image_url'] : '',
            ),
            'api' => array(
                'post_url' => esc_url_raw(admin_url('admin-post.php')),
            ),
            'labels' => array(
                'start' => __('Start', 'yesorno'),
                'yes' => __('YES', 'yesorno'),
                'no' => __('NO', 'yesorno'),
                'result' => __('View Details', 'yesorno'),
                'restart' => __('Restart', 'yesorno'),
                'progress' => __('Progress', 'yesorno'),
                'completed' => __('Your result is ready.', 'yesorno'),
                'result_missing' => __('Result link is not configured.', 'yesorno'),
            ),
            'messages' => array(
                'start_intro' => __('Follow your first instinct.', 'yesorno'),
                'start_total' => __('8 questions total', 'yesorno'),
                'start_hint' => __('Do not overthink, pick the side that feels stronger.', 'yesorno'),
                'start_relief' => __('You can review your pattern in the result.', 'yesorno'),
                'guide_line_1' => __('Swipe the card or use the buttons below.', 'yesorno'),
                'guide_line_2' => __('Left means NO, right means YES.', 'yesorno'),
                'analyzing_1' => __('Organizing your choice pattern...', 'yesorno'),
                'analyzing_2' => __('Comparing your response flow...', 'yesorno'),
                'analyzing_3' => __('Interpreting your result card...', 'yesorno'),
                'analyzing_4' => __('Your result is ready.', 'yesorno'),
                'result_summary_fallback' => __('We prepared a short summary from your answer flow.', 'yesorno'),
            ),
        ));

        $payload = array(
            'slug_alias' => $test['slug_alias'],
        );

        $instance_id = 'yesorno-' . wp_generate_password(8, false, false);
        ob_start();
        ?>
        <div class="taro-fortune-root" id="<?php echo esc_attr($instance_id); ?>" data-bootstrap="<?php echo esc_attr(wp_json_encode($payload, JSON_UNESCAPED_UNICODE)); ?>">
            
            <?php 
            // [추가됨] PC에서 넘어온 커스텀 HTML 템플릿이 있다면 그것을 통째로 렌더링
            if (!empty($test['html_template'])) {
                // 관리자가 입력한 안전한 HTML이므로 출력 허용
                echo $test['html_template'];
            } else {
                // 커스텀 템플릿이 없을 경우 기존의 기본 레이아웃을 렌더링
            ?>
            <header class="taro-fortune-header">
                <h2 class="taro-fortune-title"></h2>
                <p class="taro-fortune-subtitle"></p>
            </header>

            <section class="taro-fortune-stage taro-stage-start is-active">
                <div class="taro-start-card-wrap">
                    <div class="taro-start-card-slot">
                        <div class="taro-card no-flip taro-start-card" role="button" tabindex="0" aria-label="<?php esc_attr_e('Start card', 'yesorno'); ?>">
                            <span class="taro-card-inner">
                                <span class="taro-card-face taro-card-front taro-start-card-face">
                                    <span class="taro-front-overlay"></span>
                                    <span class="taro-prism"></span>
                                    <span class="taro-swipe-indicator taro-swipe-yes"></span>
                                    <span class="taro-swipe-indicator taro-swipe-no"></span>
                                    <span class="taro-content">
                                        <h3 class="taro-start-intro"></h3>
                                        <p class="taro-start-total"></p>
                                    </span>
                                </span>
                            </span>
                        </div>
                    </div>
                    <p class="taro-start-hint"></p>
                    <p class="taro-start-relief"></p>
                </div>
                <button class="taro-stage-start-button" type="button"></button>
            </section>

            <section class="taro-fortune-stage taro-stage-progress">
                <div class="taro-progress-wrap">
                    <div class="taro-progress-head"><span class="taro-progress-label"></span><span class="taro-progress-text">0/0</span></div>
                    <div class="taro-progress-bar"><span class="taro-progress-fill"></span></div>
                </div>
                <div class="taro-fortune-cards taro-fortune-cards-single"></div>
                <div class="taro-progress-guides">
                    <p class="taro-progress-guide-1"></p>
                    <p class="taro-progress-guide-2"></p>
                </div>
                <div class="taro-answer-controls">
                    <button class="taro-answer-btn taro-answer-no" type="button"></button>
                    <button class="taro-answer-btn taro-answer-yes" type="button"></button>
                </div>
            </section>

            <section class="taro-fortune-stage taro-stage-analyzing">
                <p class="taro-analyzing-text"></p>
                <div class="taro-analyzing-dots"><span></span><span></span><span></span></div>
                <div class="taro-analyzing-bar"><span class="taro-analyzing-fill"></span></div>
            </section>

            <section class="taro-fortune-stage taro-stage-complete">
                <div class="taro-result-preview" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open result details', 'yesorno'); ?>">
                    <div class="taro-card no-flip taro-result-card">
                        <span class="taro-card-inner">
                            <span class="taro-card-face taro-card-front taro-result-card-face">
                                <span class="taro-front-overlay"></span>
                                <span class="taro-prism"></span>
                                <span class="taro-content">
                                    <h3 class="taro-result-title"></h3>
                                    <p class="taro-result-cta-text"></p>
                                </span>
                            </span>
                        </span>
                    </div>
                </div>
                <p class="taro-complete-text"></p>
                <button class="taro-restart-button" type="button"></button>
            </section>
            <?php 
            } // end of template switch 
            ?>

            <footer class="taro-fortune-footer"><?php echo esc_html(html_entity_decode($settings['copyright'], ENT_QUOTES, 'UTF-8')); ?></footer>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_public_bootstrap() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(array('code' => 'method_not_allowed', 'message' => __('Only POST is allowed.', 'yesorno')), 405);
        }

        $slug = isset($_POST['slug']) ? sanitize_key((string) $_POST['slug']) : '';
        if ($slug === '') {
            wp_send_json_error(array('code' => 'invalid_slug', 'message' => __('Invalid test slug.', 'yesorno')), 400);
        }

        $test = $this->data_store->get_test_by_slug_or_id($slug);
        if (!$test) {
            wp_send_json_error(array('code' => 'not_found', 'message' => __('Test not found.', 'yesorno')), 404);
        }

        $session_token = $this->issue_session_token($test['slug_alias']);
        $session_cards = $this->serialize_session_cards($this->select_session_cards($test));

        wp_send_json_success(array(
            'slug_alias' => $test['slug_alias'],
            'card_back_image_url' => isset($test['card_back_image_url']) ? $test['card_back_image_url'] : '',
            'start_image_url' => !empty($test['start_image_url']) ? $test['start_image_url'] : (isset($test['card_back_image_url']) ? $test['card_back_image_url'] : ''),
            'front_overlay_background' => isset($test['front_overlay_background']) ? $test['front_overlay_background'] : '',
            'back_overlay_background' => isset($test['back_overlay_background']) ? $test['back_overlay_background'] : '',
            'prism_background' => isset($test['prism_background']) ? $test['prism_background'] : '',
            'prism_mix_blend_mode' => isset($test['prism_mix_blend_mode']) ? $test['prism_mix_blend_mode'] : '',
            'display_count' => max(8, min(10, isset($test['display_count']) ? absint($test['display_count']) : 8)),
            'session_token' => $session_token,
            'cards' => array_values($session_cards),
        ), 200);
    }

    public function handle_public_result() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(array('code' => 'method_not_allowed', 'message' => __('Only POST is allowed.', 'yesorno')), 405);
        }

        $slug = isset($_POST['slug']) ? sanitize_key((string) $_POST['slug']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field((string) $_POST['token']) : '';
        if ($slug === '' || !$this->validate_session_token($slug, $token)) {
            wp_send_json_error(array('code' => 'invalid_session', 'message' => __('Invalid session token.', 'yesorno')), 403);
        }

        if ($this->is_rate_limited($slug)) {
            wp_send_json_error(array('code' => 'rate_limited', 'message' => __('Too many requests. Please try again shortly.', 'yesorno')), 429);
        }

        $test = $this->data_store->get_test_by_slug_or_id($slug);
        if (!$test) {
            wp_send_json_error(array('code' => 'not_found', 'message' => __('Test not found.', 'yesorno')), 404);
        }

        $answers_raw = isset($_POST['answers']) ? wp_unslash((string) $_POST['answers']) : '';
        $answers = json_decode($answers_raw, true);
        if (!is_array($answers) || empty($answers)) {
            wp_send_json_error(array('code' => 'invalid_answers', 'message' => __('Answers are required.', 'yesorno')), 400);
        }

        $result = $this->compute_result_payload($test, $answers);
        wp_send_json_success(array('result' => $result), 200);
    }

    private function compute_result_payload($test, $answers) {
        $cards_by_id = array();
        foreach ((array) $test['cards'] as $card) {
            if (!empty($card['id']) && !empty($card['active'])) {
                $cards_by_id[$card['id']] = $card;
            }
        }

        $scores = array();
        foreach ($answers as $item) {
            if (!is_array($item)) {
                continue;
            }
            $card_id = isset($item['card_id']) ? sanitize_title((string) $item['card_id']) : '';
            $answer = isset($item['answer']) ? sanitize_key((string) $item['answer']) : '';
            if ($card_id === '' || ($answer !== 'yes' && $answer !== 'no') || !isset($cards_by_id[$card_id])) {
                continue;
            }

            $card = $cards_by_id[$card_id];
            $map = ($answer === 'yes')
                ? (isset($card['score_yes']) && is_array($card['score_yes']) ? $card['score_yes'] : array())
                : (isset($card['score_no']) && is_array($card['score_no']) ? $card['score_no'] : array());

            foreach ($map as $code => $score) {
                $key = sanitize_key((string) $code);
                if ($key === '') {
                    continue;
                }
                $scores[$key] = (int) (isset($scores[$key]) ? $scores[$key] : 0) + (int) $score;
            }
        }

        $results = array_values(array_filter((array) $test['results'], function ($result) {
            return !empty($result['active']);
        }));
        if (empty($results)) {
            $results = array_values((array) $test['results']);
        }

        if (empty($results)) {
            return array(
                'label' => __('Your result is ready.', 'yesorno'),
                'result_summary' => __('We prepared a short summary from your answer flow.', 'yesorno'),
                'result_image_url' => !empty($test['card_back_image_url']) ? $test['card_back_image_url'] : '',
                'result_url' => '',
                'result_cta_label' => __('View Details', 'yesorno'),
            );
        }

        $best = $results[0];
        $best_score = isset($scores[$best['result_code']]) ? (int) $scores[$best['result_code']] : 0;
        foreach ($results as $result) {
            $code = isset($result['result_code']) ? sanitize_key((string) $result['result_code']) : '';
            $score = isset($scores[$code]) ? (int) $scores[$code] : 0;
            if ($score > $best_score) {
                $best = $result;
                $best_score = $score;
            }
        }

        return array(
            'label' => !empty($best['label']) ? $best['label'] : __('Your result is ready.', 'yesorno'),
            'result_summary' => !empty($best['result_summary']) ? $best['result_summary'] : __('We prepared a short summary from your answer flow.', 'yesorno'),
            'result_image_url' => !empty($best['result_image_url']) ? $best['result_image_url'] : (!empty($test['card_back_image_url']) ? $test['card_back_image_url'] : ''),
            'result_url' => !empty($best['result_url']) ? $best['result_url'] : '',
            'result_cta_label' => !empty($best['result_cta_label']) ? $best['result_cta_label'] : __('View Details', 'yesorno'),
        );
    }

    private function select_session_cards($test) {
        $active_cards = array_values(array_filter((array) $test['cards'], function ($card) {
            return !empty($card['active']);
        }));
        $quotas = isset($test['groups_quota']) && is_array($test['groups_quota']) ? $test['groups_quota'] : array();
        $picked = array();

        foreach ($quotas as $group => $quota) {
            $group_key = (string) $group;
            $q = max(0, (int) $quota);
            if ($q <= 0) {
                continue;
            }
            $pool = array_values(array_filter($active_cards, function ($card) use ($group_key) {
                return isset($card['group']) && (string) $card['group'] === $group_key;
            }));
            $pool = $this->shuffle_items($pool);
            $picked = array_merge($picked, array_slice($pool, 0, $q));
        }

        $seen = array();
        foreach ($picked as $card) {
            if (!empty($card['id'])) {
                $seen[$card['id']] = true;
            }
        }

        $remain = array_values(array_filter($active_cards, function ($card) use ($seen) {
            return empty($card['id']) || !isset($seen[$card['id']]);
        }));
        $display_count = max(8, min(10, isset($test['display_count']) ? absint($test['display_count']) : 8));
        if (count($picked) < $display_count) {
            $picked = array_merge($picked, array_slice($this->shuffle_items($remain), 0, $display_count - count($picked)));
        }

        return array_slice($this->shuffle_items($picked), 0, $display_count);
    }

    private function serialize_session_cards($cards) {
        return array_map(function ($card) {
            return array(
                'id' => isset($card['id']) ? $card['id'] : '',
                'image_url' => isset($card['image_url']) ? $card['image_url'] : '',
                'question_text' => isset($card['question_text']) ? $card['question_text'] : '',
                'sub_text' => isset($card['sub_text']) ? $card['sub_text'] : '',
                'group' => isset($card['group']) ? $card['group'] : '',
                'front_overlay_background' => isset($card['front_overlay_background']) ? $card['front_overlay_background'] : '',
                'back_overlay_background' => isset($card['back_overlay_background']) ? $card['back_overlay_background'] : '',
                'prism_background' => isset($card['prism_background']) ? $card['prism_background'] : '',
                'prism_mix_blend_mode' => isset($card['prism_mix_blend_mode']) ? $card['prism_mix_blend_mode'] : '',
            );
        }, is_array($cards) ? $cards : array());
    }

    private function shuffle_items($items) {
        $shuffled = array_values(is_array($items) ? $items : array());
        if (count($shuffled) > 1) {
            shuffle($shuffled);
        }
        return $shuffled;
    }

    private function issue_session_token($slug_alias) {
        $slug = sanitize_key((string) $slug_alias);
        $token = wp_generate_password(20, false, false);
        set_transient('yesorno_st_' . $token, $slug, HOUR_IN_SECONDS);
        return $token;
    }

    private function validate_session_token($slug_alias, $token) {
        $slug = sanitize_key((string) $slug_alias);
        $key = sanitize_text_field((string) $token);
        if ($slug === '' || $key === '') {
            return false;
        }
        $stored = get_transient('yesorno_st_' . $key);
        return is_string($stored) && sanitize_key($stored) === $slug;
    }

    private function is_rate_limited($slug_alias) {
        $slug = sanitize_key((string) $slug_alias);
        $ip = $this->get_client_ip();
        $key = 'yesorno_rl_' . md5($slug . '|' . $ip);
        $count = (int) get_transient($key);
        if ($count >= 80) {
            return true;
        }
        set_transient($key, $count + 1, 60);
        return false;
    }

    private function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '0.0.0.0';
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}