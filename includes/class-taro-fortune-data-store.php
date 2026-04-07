<?php

if (!defined('ABSPATH')) {
    exit;
}

class YesOrNo_Data_Store {
    private $post_type = 'yesorno_test';
    private $settings_option = 'yesorno_settings';
    private $migrated_option = 'yesorno_migrated_from_legacy';
    private $default_back_image_url = 'https://koreahi.com/wp-content/uploads/2026/04/b2.jpg';
    private $default_front_overlay_background = 'linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))';
    private $default_back_overlay_background = 'linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))';
    private $default_prism_background = 'linear-gradient(110deg, rgba(255, 255, 255, 0.05) 18%, rgba(255, 107, 129, 0.3) 32%, rgba(92, 245, 255, 0.26) 48%, rgba(255, 201, 107, 0.34) 64%, rgba(255, 255, 255, 0.05) 78%)';
    private $default_prism_mix_blend_mode = 'screen';
    private $allowed_prism_mix_blend_modes = array('screen', 'normal', 'multiply', 'overlay', 'soft-light', 'hard-light', 'color-dodge', 'lighten');

    public function bootstrap() {
        $this->register_post_type();
        $this->migrate_legacy_json_data();
    }

    public function register_post_type() {
        register_post_type($this->post_type, array(
            'label' => __('YesOrNo Tests', 'yesorno'),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function get_tests() {
        $posts = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $tests = array();
        foreach ($posts as $post) {
            $payload = $this->build_test_payload($post->ID);
            if ($payload) {
                $tests[] = $payload;
            }
        }

        return $tests;
    }

    public function get_test($post_id) {
        $post = get_post(absint($post_id));
        if (!$post || $post->post_type !== $this->post_type) {
            return null;
        }
        return $this->build_test_payload($post->ID);
    }

    public function get_test_by_slug_or_id($value) {
        $value = sanitize_text_field((string) $value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $by_id = $this->get_test((int) $value);
            if ($by_id) {
                return $by_id;
            }
        }

        $slug = sanitize_key($value);
        foreach ($this->get_tests() as $test) {
            if ($test['slug_alias'] === $slug || $test['id'] === $slug) {
                return $test;
            }
        }

        return null;
    }

    public function get_default_test() {
        $tests = $this->get_tests();
        if (empty($tests)) {
            return null;
        }
        foreach ($tests as $test) {
            if (!empty($test['active'])) {
                return $test;
            }
        }
        return $tests[0];
    }

    public function save_test($payload, $post_id = 0) {
        $normalized = $this->normalize_test_payload($payload);
        if (!$normalized) {
            return 0;
        }

        $postarr = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'post_title' => $normalized['title'],
        );

        if ($post_id > 0) {
            $postarr['ID'] = absint($post_id);
            $saved = wp_update_post($postarr, true);
        } else {
            $saved = wp_insert_post($postarr, true);
        }

        if (is_wp_error($saved) || !$saved) {
            return 0;
        }

        $saved_id = (int) $saved;
        update_post_meta($saved_id, '_yesorno_basic', $normalized['basic']);
        update_post_meta($saved_id, '_yesorno_cards', $normalized['cards']);
        update_post_meta($saved_id, '_yesorno_results', $normalized['results']);

        return $saved_id;
    }

    public function delete_test($post_id) {
        return (bool) wp_delete_post(absint($post_id), true);
    }

    public function get_settings() {
        $settings = get_option($this->settings_option, array());
        if (!is_array($settings)) {
            $settings = array();
        }

        return wp_parse_args($settings, array(
            'default_card_back_image_url' => $this->default_back_image_url,
            'copyright' => '&#9426; KoreaHi.com',
        ));
    }

    public function save_settings($settings) {
        $merged = wp_parse_args((array) $settings, $this->get_settings());
        return update_option($this->settings_option, $merged, false);
    }

    public function generate_slug_alias($title) {
        $slug = sanitize_title((string) $title);
        if ($slug === '') {
            $slug = 'yesorno-' . substr(md5($title . microtime(true)), 0, 8);
        }
        return $slug;
    }

    public function parse_json_import($json) {
        $decoded = json_decode((string) $json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return new WP_Error('invalid_json', __('Invalid JSON payload.', 'yesorno'));
        }

        if (!isset($decoded['basic']) || !isset($decoded['cards']) || !isset($decoded['results'])) {
            return new WP_Error('missing_sections', __('JSON must include basic, cards, results.', 'yesorno'));
        }

        $payload = array(
            'id' => isset($decoded['basic']['id']) ? $decoded['basic']['id'] : '',
            'slug_alias' => isset($decoded['basic']['slug_alias']) ? $decoded['basic']['slug_alias'] : '',
            'title' => isset($decoded['basic']['title']) ? $decoded['basic']['title'] : '',
            'description' => isset($decoded['basic']['description']) ? $decoded['basic']['description'] : '',
            'card_back_image_url' => isset($decoded['basic']['card_back_image_url']) ? $decoded['basic']['card_back_image_url'] : '',
            'start_image_url' => isset($decoded['basic']['start_image_url']) ? $decoded['basic']['start_image_url'] : '',
            'front_overlay_background' => isset($decoded['basic']['front_overlay_background']) ? $decoded['basic']['front_overlay_background'] : '',
            'back_overlay_background' => isset($decoded['basic']['back_overlay_background']) ? $decoded['basic']['back_overlay_background'] : '',
            'prism_background' => isset($decoded['basic']['prism_background']) ? $decoded['basic']['prism_background'] : '',
            'prism_mix_blend_mode' => isset($decoded['basic']['prism_mix_blend_mode']) ? $decoded['basic']['prism_mix_blend_mode'] : '',
            'display_count' => isset($decoded['basic']['display_count']) ? $decoded['basic']['display_count'] : 8,
            'pool_count' => isset($decoded['basic']['pool_count']) ? $decoded['basic']['pool_count'] : 16,
            'groups_quota' => isset($decoded['basic']['groups_quota']) ? $decoded['basic']['groups_quota'] : array(),
            'active' => isset($decoded['basic']['active']) ? $decoded['basic']['active'] : 1,
            'cards' => is_array($decoded['cards']) ? $decoded['cards'] : array(),
            'results' => is_array($decoded['results']) ? $decoded['results'] : array(),
        );

        $normalized = $this->normalize_test_payload($payload);
        if (!$normalized) {
            return new WP_Error('invalid_payload', __('Invalid test payload.', 'yesorno'));
        }

        if (empty($normalized['results'])) {
            return new WP_Error('results_required', __('At least one result is required.', 'yesorno'));
        }

        $active_results = array_filter($normalized['results'], function ($result) {
            return !empty($result['active']);
        });
        if (empty($active_results)) {
            return new WP_Error('active_result_required', __('At least one active result is required.', 'yesorno'));
        }

        return $normalized;
    }
    private function build_test_payload($post_id) {
        $basic = get_post_meta($post_id, '_yesorno_basic', true);
        $cards = get_post_meta($post_id, '_yesorno_cards', true);
        $results = get_post_meta($post_id, '_yesorno_results', true);

        if (!is_array($basic)) { $basic = array(); }
        if (!is_array($cards)) { $cards = array(); }
        if (!is_array($results)) { $results = array(); }

        $title = isset($basic['title']) ? sanitize_text_field((string) $basic['title']) : get_the_title($post_id);
        if ($title === '') {
            return null;
        }

        $slug_alias = isset($basic['slug_alias']) ? sanitize_key((string) $basic['slug_alias']) : $this->generate_slug_alias($title);
        $id = isset($basic['id']) ? sanitize_key((string) $basic['id']) : ('test-' . $post_id);

        return array(
            'post_id' => (int) $post_id,
            'id' => $id,
            'slug_alias' => $slug_alias,
            'title' => $title,
            'description' => isset($basic['description']) ? sanitize_textarea_field((string) $basic['description']) : '',
            'card_back_image_url' => isset($basic['card_back_image_url']) ? esc_url_raw((string) $basic['card_back_image_url']) : '',
            'start_image_url' => isset($basic['start_image_url']) ? esc_url_raw((string) $basic['start_image_url']) : '',
            'front_overlay_background' => $this->normalize_background_string(isset($basic['front_overlay_background']) ? $basic['front_overlay_background'] : '', $this->default_front_overlay_background, true),
            'back_overlay_background' => $this->normalize_background_string(isset($basic['back_overlay_background']) ? $basic['back_overlay_background'] : '', $this->default_back_overlay_background, true),
            'prism_background' => $this->normalize_background_string(isset($basic['prism_background']) ? $basic['prism_background'] : '', $this->default_prism_background, true),
            'prism_mix_blend_mode' => $this->normalize_prism_mix_blend_mode(isset($basic['prism_mix_blend_mode']) ? $basic['prism_mix_blend_mode'] : '', $this->default_prism_mix_blend_mode, true),
            'display_count' => max(8, min(10, isset($basic['display_count']) ? absint($basic['display_count']) : 8)),
            'pool_count' => max(16, min(24, isset($basic['pool_count']) ? absint($basic['pool_count']) : 16)),
            'groups_quota' => $this->normalize_groups_quota(isset($basic['groups_quota']) ? $basic['groups_quota'] : array()),
            'active' => !empty($basic['active']) ? 1 : 0,
            'cards' => array_values(array_filter(array_map(array($this, 'normalize_card'), $cards))),
            'results' => array_values(array_filter(array_map(array($this, 'normalize_result'), $results))),
        );
    }

    private function normalize_test_payload($payload) {
        if (!is_array($payload)) {
            return null;
        }

        $title = isset($payload['title']) ? sanitize_text_field((string) $payload['title']) : '';
        if ($title === '') {
            return null;
        }

        $slug_alias = isset($payload['slug_alias']) ? sanitize_key((string) $payload['slug_alias']) : '';
        if ($slug_alias === '') {
            $slug_alias = $this->generate_slug_alias($title);
        }

        $id = isset($payload['id']) ? sanitize_key((string) $payload['id']) : '';
        if ($id === '') {
            $id = sanitize_key($slug_alias . '-' . wp_generate_password(4, false, false));
        }

        $cards = isset($payload['cards']) && is_array($payload['cards']) ? array_values(array_filter(array_map(array($this, 'normalize_card'), $payload['cards']))) : array();
        $results = isset($payload['results']) && is_array($payload['results']) ? array_values(array_filter(array_map(array($this, 'normalize_result'), $payload['results']))) : array();

        return array(
            'title' => $title,
            'basic' => array(
                'id' => $id,
                'title' => $title,
                'slug_alias' => $slug_alias,
                'description' => isset($payload['description']) ? sanitize_textarea_field((string) $payload['description']) : '',
                'card_back_image_url' => isset($payload['card_back_image_url']) ? esc_url_raw((string) $payload['card_back_image_url']) : '',
                'start_image_url' => isset($payload['start_image_url']) ? esc_url_raw((string) $payload['start_image_url']) : '',
                'front_overlay_background' => $this->normalize_background_string(isset($payload['front_overlay_background']) ? $payload['front_overlay_background'] : '', $this->default_front_overlay_background, true),
                'back_overlay_background' => $this->normalize_background_string(isset($payload['back_overlay_background']) ? $payload['back_overlay_background'] : '', $this->default_back_overlay_background, true),
                'prism_background' => $this->normalize_background_string(isset($payload['prism_background']) ? $payload['prism_background'] : '', $this->default_prism_background, true),
                'prism_mix_blend_mode' => $this->normalize_prism_mix_blend_mode(isset($payload['prism_mix_blend_mode']) ? $payload['prism_mix_blend_mode'] : '', $this->default_prism_mix_blend_mode, true),
                'display_count' => max(8, min(10, isset($payload['display_count']) ? absint($payload['display_count']) : 8)),
                'pool_count' => max(16, min(24, isset($payload['pool_count']) ? absint($payload['pool_count']) : 16)),
                'groups_quota' => $this->normalize_groups_quota(isset($payload['groups_quota']) ? $payload['groups_quota'] : array()),
                'active' => !empty($payload['active']) ? 1 : 0,
            ),
            'cards' => $cards,
            'results' => $results,
        );
    }

    private function normalize_groups_quota($value) {
        $out = array();
        if (!is_array($value)) {
            return $out;
        }
        foreach ($value as $group => $quota) {
            $g = sanitize_text_field((string) $group);
            $q = absint($quota);
            if ($g !== '' && $q > 0) {
                $out[$g] = $q;
            }
        }
        return $out;
    }

    private function normalize_card($card) {
        if (!is_array($card)) {
            return null;
        }
        $question_text = isset($card['question_text']) ? sanitize_text_field((string) $card['question_text']) : '';
        if ($question_text === '' && isset($card['description'])) {
            $question_text = sanitize_text_field((string) $card['description']);
        }
        if ($question_text === '') {
            return null;
        }
        $id = isset($card['id']) ? sanitize_title((string) $card['id']) : '';
        if ($id === '') {
            $id = sanitize_title($question_text . '-' . wp_generate_password(5, false, false));
        }

        return array(
            'id' => $id,
            'image_url' => isset($card['image_url']) ? esc_url_raw((string) $card['image_url']) : '',
            'title' => isset($card['title']) ? sanitize_text_field((string) $card['title']) : '',
            'description' => isset($card['description']) ? sanitize_textarea_field((string) $card['description']) : '',
            'question_text' => $question_text,
            'sub_text' => isset($card['sub_text']) ? sanitize_textarea_field((string) $card['sub_text']) : '',
            'group' => isset($card['group']) ? sanitize_text_field((string) $card['group']) : '',
            'front_overlay_background' => $this->normalize_background_string(isset($card['front_overlay_background']) ? $card['front_overlay_background'] : '', '', false),
            'back_overlay_background' => $this->normalize_background_string(isset($card['back_overlay_background']) ? $card['back_overlay_background'] : '', '', false),
            'prism_background' => $this->normalize_background_string(isset($card['prism_background']) ? $card['prism_background'] : '', '', false),
            'prism_mix_blend_mode' => $this->normalize_prism_mix_blend_mode(isset($card['prism_mix_blend_mode']) ? $card['prism_mix_blend_mode'] : '', '', false),
            'score_yes' => $this->normalize_score_map(isset($card['score_yes']) ? $card['score_yes'] : array()),
            'score_no' => $this->normalize_score_map(isset($card['score_no']) ? $card['score_no'] : array()),
            'active' => !empty($card['active']) ? 1 : 0,
        );
    }

    private function normalize_result($result) {
        if (!is_array($result)) {
            return null;
        }
        $code = isset($result['result_code']) ? sanitize_key((string) $result['result_code']) : '';
        $label = isset($result['label']) ? sanitize_text_field((string) $result['label']) : '';
        if ($code === '' || $label === '') {
            return null;
        }
        $post_id = isset($result['post_id']) ? absint($result['post_id']) : 0;
        $result_url = $post_id > 0 ? get_permalink($post_id) : '';
        if (!$result_url && isset($result['result_url'])) {
            $result_url = esc_url_raw((string) $result['result_url']);
        }

        $result_image_url = '';
        if (isset($result['result_image_url'])) {
            $result_image_url = esc_url_raw((string) $result['result_image_url']);
        } elseif (isset($result['image_url'])) {
            $result_image_url = esc_url_raw((string) $result['image_url']);
        }

        $result_summary = isset($result['result_summary']) ? sanitize_textarea_field((string) $result['result_summary']) : '';
        $result_cta_label = isset($result['result_cta_label']) ? sanitize_text_field((string) $result['result_cta_label']) : '';
        if ($result_cta_label === '') {
            $result_cta_label = 'View Details';
        }

        return array(
            'result_code' => $code,
            'label' => $label,
            'post_id' => $post_id,
            'result_url' => $result_url ? esc_url_raw($result_url) : '',
            'result_image_url' => $result_image_url,
            'result_summary' => $result_summary,
            'result_cta_label' => $result_cta_label,
            'active' => !empty($result['active']) ? 1 : 0,
        );
    }

    private function normalize_score_map($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            return array();
        }
        $out = array();
        foreach ($value as $code => $score) {
            $key = sanitize_key((string) $code);
            if ($key !== '') {
                $out[$key] = (int) $score;
            }
        }
        return $out;
    }

    private function normalize_background_string($value, $fallback = '', $use_fallback_when_empty = true) {
        if (!is_scalar($value)) {
            $value = '';
        }

        $normalized = trim((string) wp_strip_all_tags((string) $value, true));
        if ($normalized === '') {
            return $use_fallback_when_empty ? (string) $fallback : '';
        }

        return $normalized;
    }

    private function normalize_prism_mix_blend_mode($value, $fallback = 'screen', $use_fallback_when_empty = true) {
        if (!is_scalar($value)) {
            $value = '';
        }

        $normalized = strtolower(trim((string) wp_strip_all_tags((string) $value, true)));
        if ($normalized === '') {
            return $use_fallback_when_empty ? (string) $fallback : '';
        }

        if (!in_array($normalized, $this->allowed_prism_mix_blend_modes, true)) {
            return $use_fallback_when_empty ? (string) $fallback : '';
        }

        return $normalized;
    }

    private function migrate_legacy_json_data() {
        if (get_option($this->migrated_option, false)) {
            return;
        }

        $legacy_path = trailingslashit(YESORNO_DATA_DIR) . 'fortunes.json';
        if (!file_exists($legacy_path)) {
            update_option($this->migrated_option, 1, false);
            return;
        }

        $raw = file_get_contents($legacy_path);
        $legacy = json_decode((string) $raw, true);
        if (!is_array($legacy)) {
            update_option($this->migrated_option, 1, false);
            return;
        }

        foreach ($legacy as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cards = array();
            if (isset($item['cards']) && is_array($item['cards'])) {
                foreach ($item['cards'] as $legacy_card) {
                    $cards[] = array(
                        'id' => isset($legacy_card['id']) ? $legacy_card['id'] : '',
                        'image_url' => isset($legacy_card['image_url']) ? $legacy_card['image_url'] : '',
                        'title' => isset($legacy_card['title']) ? $legacy_card['title'] : '',
                        'question_text' => isset($legacy_card['description']) ? $legacy_card['description'] : '',
                        'sub_text' => '',
                        'group' => 'A',
                        'score_yes' => array('default' => 1),
                        'score_no' => array('default' => 0),
                        'active' => 1,
                    );
                }
            }

            $this->save_test(array(
                'title' => isset($item['title']) ? $item['title'] : 'YesOrNo Test',
                'slug_alias' => isset($item['slug_alias']) ? $item['slug_alias'] : '',
                'description' => isset($item['description']) ? $item['description'] : '',
                'card_back_image_url' => isset($item['card_back_image_url']) ? $item['card_back_image_url'] : $this->default_back_image_url,
                'start_image_url' => isset($item['card_back_image_url']) ? $item['card_back_image_url'] : $this->default_back_image_url,
                'display_count' => 8,
                'pool_count' => 16,
                'groups_quota' => array('A' => 2, 'B' => 2, 'C' => 2, 'D' => 1, 'E' => 1),
                'active' => 1,
                'cards' => $cards,
                'results' => array(array('result_code' => 'default', 'label' => 'Default Result', 'post_id' => 0, 'result_url' => '', 'result_image_url' => '', 'result_summary' => '', 'result_cta_label' => 'View Details', 'active' => 1)),
            ));
        }

        update_option($this->migrated_option, 1, false);
    }
}
