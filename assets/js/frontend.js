(function () {
  var config = window.YesOrNoConfig || {};
  var apiPostUrl = String((config.api && config.api.post_url) || "/wp-admin/admin-post.php").trim();

  // 안전한 텍스트/URL 출력을 위한 헬퍼 함수
  function safe(value) {
    return String(value || "").replace(/[&<>\"']/g, function (m) {
      return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m];
    });
  }
  function safeUrl(value) {
    return String(value || "").trim().replace(/["'()\\\s]/g, function (m) {
      return m === " " ? "%20" : "\\" + m;
    });
  }

  // 서버 API 통신
  function postAction(action, payload) {
    var form = new URLSearchParams();
    form.append("action", String(action || ""));
    Object.keys(payload || {}).forEach(function (key) { form.append(key, String(payload[key] || "")); });
    return fetch(apiPostUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: form.toString()
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok || !json || json.success !== true) throw new Error("HTTP " + res.status);
        return json.data || {};
      });
    });
  }

  // =====================================================================
  // 시스템 초기화
  // =====================================================================
  function init(root) {
    var bootstrapConfig = {};
    try { bootstrapConfig = JSON.parse(root.getAttribute("data-bootstrap") || "{}"); } catch (e) {}
    var slugAlias = String(bootstrapConfig.slug_alias || "").trim();
    
    // UI 이미지 에셋 저장소 (기본값 설정)
    var uiImages = bootstrapConfig.ui_images || config.ui_images || {};

    // 1. EVENT BUS (브릿지)
    var EventBus = {
      on: function(event, callback) { root.addEventListener(event, callback); },
      emit: function(event, detail) { root.dispatchEvent(new CustomEvent(event, { detail: detail || {} })); }
    };

    // 2. ENGINE LAYER (게임 로직)
    var Engine = {
      stateKey: "yesorno_state_" + (slugAlias || "default"),
      cardsKey: "yesorno_cards_" + (slugAlias || "default"),
      state: { ids: [], answers: [], idx: 0, status: "start", session_token: "", finalResult: null },
      sessionCards: [],

      init: function() {
        EventBus.on('ui:request_boot', this.handleBootRequest.bind(this));
        EventBus.on('ui:start_game', this.handleStartGame.bind(this));
        EventBus.on('ui:answer_selected', this.handleAnswerSelected.bind(this));
        EventBus.on('ui:card_exit_complete', this.handleCardExitComplete.bind(this));
        EventBus.on('ui:restart_game', this.handleRestartGame.bind(this));
        
        // 초기 데이터 요청
        EventBus.emit('ui:request_boot');
      },

      getCurrentCard: function() {
        var id = this.state.ids[this.state.idx];
        return this.sessionCards.find(function(c) { return c.id === id; });
      },

      handleBootRequest: function() {
        var self = this;
        postAction("yesorno_bootstrap", { slug: slugAlias }).then(function (json) {
          self.sessionCards = json.cards || [];
          self.state.session_token = json.session_token || "";
          // 서버에서 받아온 추가 이미지 정보 병합
          if (json.ui_images) Object.assign(uiImages, json.ui_images);
          
          EventBus.emit('engine:boot_complete', { uiImages: uiImages });
        }).catch(function(e) {
          console.error("Bootstrap failed", e);
        });
      },

      handleStartGame: function() {
        this.state = {
          ids: this.sessionCards.map(function (c) { return c.id; }),
          answers: [], idx: 0, status: "progress",
          session_token: this.state.session_token, finalResult: null
        };
        EventBus.emit('engine:progress_started', { state: this.state, card: this.getCurrentCard() });
      },

      handleAnswerSelected: function(e) {
        var answer = e.detail.answer;
        var dragState = e.detail.dragState;
        
        this.state.answers.push({ card_id: this.getCurrentCard().id, answer: answer });
        EventBus.emit('engine:animate_card_exit', { answer: answer, dragState: dragState, isLast: (this.state.idx >= this.state.ids.length - 1) });
      },

      handleCardExitComplete: function() {
        this.state.idx += 1;
        if (this.state.idx >= this.state.ids.length) {
          this.startAnalyzing();
        } else {
          EventBus.emit('engine:next_card_ready', { state: this.state, card: this.getCurrentCard() });
        }
      },

      startAnalyzing: function() {
        this.state.status = "analyzing";
        EventBus.emit('engine:analyzing_started', { uiImages: uiImages });
        
        var self = this;
        // 서버로 답변 전송 및 결과 요청
        postAction("yesorno_result", {
          slug: slugAlias,
          token: this.state.session_token,
          answers: JSON.stringify(this.state.answers)
        }).then(function (json) {
          self.state.finalResult = json.result || null;
          self.state.status = "complete";
          
          // 분석 애니메이션을 충분히 보여주기 위해 2.5초 대기 후 결과 표시
          setTimeout(function() {
            EventBus.emit('engine:result_ready', { result: self.state.finalResult, uiImages: uiImages });
          }, 2500);
        }).catch(function() {
          EventBus.emit('engine:result_ready', { result: null, uiImages: uiImages });
        });
      },

      handleRestartGame: function() {
        EventBus.emit('ui:request_boot');
      }
    };

    // 3. UI / SKIN LAYER (디자인 렌더링)
    var UI = {
      busy: false,
      els: {
        stageStart: root.querySelector(".taro-stage-start"),
        stageProgress: root.querySelector(".taro-stage-progress"),
        stageAnalyzing: root.querySelector(".taro-stage-analyzing"),
        stageComplete: root.querySelector(".taro-stage-complete"),
        startBtn: root.querySelector(".taro-stage-start-button"),
        startAnimContainer: root.querySelector(".taro-start-card-slot"),
        cardsContainer: root.querySelector(".taro-fortune-cards"),
        btnNo: root.querySelector(".taro-answer-no"),
        btnYes: root.querySelector(".taro-answer-yes"),
        progressText: root.querySelector(".taro-progress-text"),
        progressFill: root.querySelector(".taro-progress-fill"),
        analyzingContainer: root.querySelector(".taro-stage-analyzing"),
        resultTitle: root.querySelector(".taro-result-title"),
        resultFace: root.querySelector(".taro-result-card-face"),
        completeText: root.querySelector(".taro-complete-text"),
        resultCtaBtn: root.querySelector(".taro-result-cta-btn"), // SEO 랜딩 버튼
        restartBtn: root.querySelector(".taro-restart-button")
      },

      init: function() {
        this.bindEvents();
      },

      bindEvents: function() {
        var self = this;
        EventBus.on('engine:boot_complete', function(e) { self.renderStartStage(e.detail.uiImages); });
        EventBus.on('engine:progress_started', function(e) { self.renderProgressStage(e.detail.state, e.detail.card); });
        EventBus.on('engine:animate_card_exit', function(e) { self.animateCardExit(e.detail.answer, e.detail.dragState, e.detail.isLast); });
        EventBus.on('engine:next_card_ready', function(e) { self.renderProgressStage(e.detail.state, e.detail.card); });
        EventBus.on('engine:analyzing_started', function(e) { self.showAnalyzingStage(e.detail.uiImages); });
        EventBus.on('engine:result_ready', function(e) { self.showCompleteStage(e.detail.result, e.detail.uiImages); });

        if (this.els.startBtn) this.els.startBtn.addEventListener("click", function() { EventBus.emit('ui:start_game'); });
        if (this.els.btnNo) this.els.btnNo.addEventListener("click", function() { if(!self.busy) self.triggerAnswer('no'); });
        if (this.els.btnYes) this.els.btnYes.addEventListener("click", function() { if(!self.busy) self.triggerAnswer('yes'); });
        if (this.els.restartBtn) this.els.restartBtn.addEventListener("click", function() { EventBus.emit('ui:restart_game'); });
      },

      switchStage: function(stageClass) {
        [this.els.stageStart, this.els.stageProgress, this.els.stageAnalyzing, this.els.stageComplete].forEach(function(el) {
          if (el) el.classList.remove("is-active");
        });
        if (stageClass && this.els[stageClass]) this.els[stageClass].classList.add("is-active");
      },

      // 시작 화면: 텍스트 대신 WebP 애니메이션과 이미지 버튼 세팅
      renderStartStage: function(images) {
        this.switchStage('stageStart');
        if (this.els.startAnimContainer && images.start_anim_webp) {
          this.els.startAnimContainer.innerHTML = '<img src="' + safeUrl(images.start_anim_webp) + '" alt="Start Animation" class="taro-anim-img" />';
        }
        if (this.els.startBtn && images.btn_start) {
          this.els.startBtn.innerHTML = '<img src="' + safeUrl(images.btn_start) + '" alt="Start" />';
        }
      },

      // 진행 화면: 질문 텍스트 + 이미지 버튼 + 특수효과(effect_class) 반영
      renderProgressStage: function(state, cardData) {
        this.switchStage('stageProgress');
        this.busy = false;

        // 진행률 바 업데이트
        var total = state.ids.length;
        var cur = Math.min(state.idx + 1, total);
        if (this.els.progressText) this.els.progressText.textContent = cur + "/" + total;
        if (this.els.progressFill) this.els.progressFill.style.width = (total ? Math.round((cur / total) * 100) : 0) + "%";

        // 버튼 이미지 세팅 (한 번만 적용)
        if (this.els.btnYes && !this.els.btnYes.querySelector('img') && uiImages.btn_yes) {
          this.els.btnYes.innerHTML = '<img src="' + safeUrl(uiImages.btn_yes) + '" alt="YES" />';
        }
        if (this.els.btnNo && !this.els.btnNo.querySelector('img') && uiImages.btn_no) {
          this.els.btnNo.innerHTML = '<img src="' + safeUrl(uiImages.btn_no) + '" alt="NO" />';
        }

        if (!this.els.cardsContainer || !cardData) return;
        this.els.cardsContainer.innerHTML = "";

        // ✨ 특수효과 클래스(effect_class) 적용
        var customEffect = cardData.effect_class ? " " + safe(cardData.effect_class) : "";

        var cardEl = document.createElement("div");
        cardEl.className = "taro-card no-flip" + customEffect;
        cardEl.innerHTML =
          '<span class="taro-card-inner">' +
            '<span class="taro-card-face taro-card-front" style="background-image:url(\'' + safeUrl(cardData.image_url) + '\')">' +
              '<span class="taro-content"><h3>' + safe(cardData.question_text || "") + '</h3><p>' + safe(cardData.sub_text || "") + '</p></span>' +
            '</span>' +
          '</span>';
        
        this.els.cardsContainer.appendChild(cardEl);
        if (window.gsap) window.gsap.from(cardEl, { opacity: 0, y: 15, duration: 0.3, ease: "back.out(1.2)" });
        
        this.attachDragEvents(cardEl);
      },

      triggerAnswer: function(answer, dragState) {
        this.busy = true;
        EventBus.emit('ui:answer_selected', { answer: answer, dragState: dragState });
      },

      // GSAP을 이용한 스와이프 아웃 액션
      animateCardExit: function(answer, dragState, isLast) {
        var cardEl = this.els.cardsContainer.querySelector(".taro-card");
        if (!cardEl) { EventBus.emit('ui:card_exit_complete'); return; }

        if (window.gsap) {
          if (dragState && typeof dragState.x === "number") {
            window.gsap.set(cardEl, { x: dragState.x, rotation: Number(dragState.rotation || 0) });
          }
          window.gsap.to(cardEl, {
            x: answer === "yes" ? window.innerWidth : -window.innerWidth,
            rotation: answer === "yes" ? 15 : -15,
            opacity: 0,
            duration: 0.3,
            ease: "power2.in",
            onComplete: function() { EventBus.emit('ui:card_exit_complete'); }
          });
        } else {
          EventBus.emit('ui:card_exit_complete');
        }
      },

      attachDragEvents: function(cardEl) {
        var self = this;
        var startX = 0, currentX = 0, dragging = false;
        var threshold = 60;
        cardEl.style.touchAction = "none";

        cardEl.addEventListener("pointerdown", function(e) {
          if (self.busy) return;
          dragging = true; startX = e.clientX; currentX = 0;
          cardEl.setPointerCapture(e.pointerId);
        });

        cardEl.addEventListener("pointermove", function(e) {
          if (!dragging || self.busy) return;
          currentX = e.clientX - startX;
          cardEl.style.transform = "translateX(" + currentX + "px) rotate(" + (currentX * 0.05) + "deg)";
        });

        function endDrag() {
          if (!dragging || self.busy) return;
          dragging = false;
          if (Math.abs(currentX) >= threshold) {
            self.triggerAnswer(currentX > 0 ? "yes" : "no", { x: currentX, rotation: currentX * 0.05 });
          } else {
            if (window.gsap) window.gsap.to(cardEl, { x: 0, rotation: 0, duration: 0.2, clearProps: "transform" });
          }
        }
        cardEl.addEventListener("pointerup", endDrag);
        cardEl.addEventListener("pointercancel", endDrag);
      },

      // 분석(로딩) 화면: WebP 애니메이션 렌더링
      showAnalyzingStage: function(images) {
        this.switchStage('stageAnalyzing');
        if (this.els.analyzingContainer && images.analyzing_anim_webp) {
          this.els.analyzingContainer.innerHTML = '<img src="' + safeUrl(images.analyzing_anim_webp) + '" alt="Analyzing..." class="taro-anim-img" />';
        }
      },

      // 완료 화면: SEO 퍼널 (CTA 버튼) 연결
      showCompleteStage: function(result, images) {
        this.switchStage('stageComplete');
        
        var res = result || {};
        if (this.els.resultTitle) this.els.resultTitle.textContent = res.label || "결과가 준비되었습니다.";
        if (this.els.completeText) this.els.completeText.textContent = res.result_summary || "";
        if (this.els.resultFace && res.result_image_url) {
          this.els.resultFace.style.backgroundImage = "url('" + safeUrl(res.result_image_url) + "')";
        }

        // 재시작 버튼 이미지 셋팅
        if (this.els.restartBtn && images.btn_restart) {
          this.els.restartBtn.innerHTML = '<img src="' + safeUrl(images.btn_restart) + '" alt="Restart" />';
        }

        // ✨ SEO 퍼널의 꽃: 타겟 URL로 이동하는 CTA 버튼 렌더링
        var ctaArea = document.querySelector(".taro-result-cta-area");
        if (!ctaArea && this.els.stageComplete) {
          ctaArea = document.createElement("div");
          ctaArea.className = "taro-result-cta-area";
          this.els.stageComplete.insertBefore(ctaArea, this.els.restartBtn);
        }
        if (res.result_url && res.result_cta_label) {
          ctaArea.innerHTML = '<a href="' + safeUrl(res.result_url) + '" class="taro-cta-link" target="_blank">' + safe(res.result_cta_label) + '</a>';
        } else {
          ctaArea.innerHTML = '';
        }
      }
    };

    UI.init();
    Engine.init();
  }

  document.querySelectorAll(".taro-fortune-root").forEach(init);
})();