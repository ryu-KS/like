<?php

if (!defined('ABSPATH')) {
    exit;
}

class YesOrNo_Frontend {
    private $data_store;

    public function __construct($data_store) {
        $this->data_store = $data_store;
    }

    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode('yesorno', array($this, 'render_shortcode'));
    }

    public function register_assets() {
        wp_register_style('yesorno-frontend', YESORNO_URL . 'assets/css/frontend.css', array(), YESORNO_VERSION);
        wp_register_script('yesorno-gsap', 'https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/gsap.min.js', array(), '3.12.7', true);
        wp_register_script('yesorno-frontend', YESORNO_URL . 'assets/js/frontend.js', array('yesorno-gsap'), YESORNO_VERSION, true);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => '', 'yesorno' => ''), $atts, 'yesorno');
        $lookup = !empty($atts['yesorno']) ? $atts['yesorno'] : $atts['id'];
        
        $test = $this->data_store->get_test_by_slug_or_id($lookup);
        if (!$test) $test = $this->data_store->get_default_test();
        if (!$test) return '';

        return $this->render_test($test);
    }

    private function render_test($test) {
        wp_enqueue_style('yesorno-frontend');
        wp_enqueue_script('yesorno-frontend');

        // 커스텀 CSS 주입
        if (!empty($test['custom_css'])) {
            wp_add_inline_style('yesorno-frontend', $test['custom_css']);
        }

        wp_localize_script('yesorno-frontend', 'YesOrNoConfig', array(
            'api' => array('post_url' => admin_url('admin-post.php')),
            'labels' => array('start' => __('Start', 'yesorno'), 'yes' => __('YES', 'yesorno'), 'no' => __('NO', 'yesorno')),
        ));

        $payload = array('slug_alias' => $test['slug_alias']);
        $instance_id = 'yesorno-' . wp_generate_password(8, false, false);
        
        ob_start();
        ?>
        <div class="taro-fortune-root" id="<?php echo esc_attr($instance_id); ?>" data-bootstrap="<?php echo esc_attr(wp_json_encode($payload)); ?>">
            <?php 
            // 커스텀 HTML 템플릿이 있다면 그것을 사용하고, 없다면 기본 레이아웃 사용
            if (!empty($test['html_template'])) {
                echo $test['html_template']; 
            } else {
                ?>
                <header class="taro-fortune-header"><h2 class="taro-fortune-title"><?php echo esc_html($test['title']); ?></h2></header>
                <section class="taro-fortune-stage taro-stage-start is-active">
                    <div class="taro-start-card-wrap"><div class="taro-start-card-slot"><div class="taro-card no-flip taro-start-card"><span class="taro-card-inner"><span class="taro-card-face taro-card-front taro-start-card-face"><span class="taro-content"><h3>START</h3></span></span></span></div></div></div>
                    <button class="taro-stage-start-button" type="button">Start</button>
                </section>
                <section class="taro-fortune-stage taro-stage-progress">
                    <div class="taro-progress-wrap"><span class="taro-progress-text">0/0</span><div class="taro-progress-bar"><span class="taro-progress-fill"></span></div></div>
                    <div class="taro-fortune-cards taro-fortune-cards-single"></div>
                    <div class="taro-answer-controls"><button class="taro-answer-btn taro-answer-no">NO</button><button class="taro-answer-btn taro-answer-yes">YES</button></div>
                </section>
                <section class="taro-fortune-stage taro-stage-analyzing"><p class="taro-analyzing-text">Analyzing...</p></section>
                <section class="taro-fortune-stage taro-stage-complete"><div class="taro-result-preview"></div><button class="taro-restart-button">Restart</button></section>
                <?php
            }
            ?>
            <footer class="taro-fortune-footer">© KoreaHi.com</footer>
        </div>
        <?php
        return ob_get_clean();
    }
}