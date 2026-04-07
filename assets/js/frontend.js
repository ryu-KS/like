(function () {
  var config = window.YesOrNoConfig || {};
  var settings = config.settings || {};
  var labels = config.labels || {};
  var messages = config.messages || {};
  var api = config.api || {};
  var apiPostUrl = String(api.post_url || "/wp-admin/admin-post.php").trim();
  var defaultBack = settings.default_card_back_image_url || "";
  var DEFAULT_FRONT_OVERLAY = "linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))";
  var DEFAULT_BACK_OVERLAY = "linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))";
  var DEFAULT_PRISM = "linear-gradient(110deg, rgba(255, 255, 255, 0.05) 18%, rgba(255, 107, 129, 0.3) 32%, rgba(92, 245, 255, 0.26) 48%, rgba(255, 201, 107, 0.34) 64%, rgba(255, 255, 255, 0.05) 78%)";
  var DEFAULT_PRISM_MIX_BLEND_MODE = "screen";
  var ALLOWED_PRISM_MIX_BLEND_MODES = {
    "screen": true,
    "normal": true,
    "multiply": true,
    "overlay": true,
    "soft-light": true,
    "hard-light": true,
    "color-dodge": true,
    "lighten": true
  };

  function safe(value) {
    return String(value || "").replace(/[&<>\"']/g, function (m) {
      return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m];
    });
  }

  function safeUrl(value) {
    var url = String(value || "").trim();
    return url.replace(/["'()\\\s]/g, function (m) {
      if (m === " ") return "%20";
      return "\\" + m;
    });
  }

  function normalizeBackground(value) {
    return String(value || "").trim();
  }

  function resolveBackground(cardValue, basicValue, fallbackValue) {
    var resolvedCard = normalizeBackground(cardValue);
    if (resolvedCard) return resolvedCard;
    var resolvedBasic = normalizeBackground(basicValue);
    if (resolvedBasic) return resolvedBasic;
    return String(fallbackValue || "").trim();
  }

  function applyBackground(el, backgroundValue) {
    if (!el) return;
    var normalized = normalizeBackground(backgroundValue);
    if (!normalized) return;
    el.style.background = normalized;
  }

  function normalizeMixBlendMode(value) {
    var normalized = String(value || "").trim().toLowerCase();
    if (!normalized) return "";
    return ALLOWED_PRISM_MIX_BLEND_MODES[normalized] ? normalized : "";
  }

  function resolveMixBlendMode(cardValue, basicValue, fallbackValue) {
    var resolvedCard = normalizeMixBlendMode(cardValue);
    if (resolvedCard) return resolvedCard;
    var resolvedBasic = normalizeMixBlendMode(basicValue);
    if (resolvedBasic) return resolvedBasic;
    var resolvedFallback = normalizeMixBlendMode(fallbackValue);
    return resolvedFallback || DEFAULT_PRISM_MIX_BLEND_MODE;
  }

  function applyPrismStyle(el, backgroundValue, mixBlendModeValue) {
    if (!el) return;
    applyBackground(el, backgroundValue);
    var resolvedBlendMode = resolveMixBlendMode(mixBlendModeValue, "", DEFAULT_PRISM_MIX_BLEND_MODE);
    el.style.mixBlendMode = resolvedBlendMode;
    el.style.setProperty("mix-blend-mode", resolvedBlendMode);
  }

  function cardElement(card, backUrl, basicBackgrounds) {
    var resolvedBack = backUrl || defaultBack || "";
    var resolvedFront = card.image_url || resolvedBack;
    var frontOverlay = resolveBackground(card.front_overlay_background, basicBackgrounds.front_overlay_background, DEFAULT_FRONT_OVERLAY);
    var backOverlay = resolveBackground(card.back_overlay_background, basicBackgrounds.back_overlay_background, DEFAULT_BACK_OVERLAY);
    var prismBackground = resolveBackground(card.prism_background, basicBackgrounds.prism_background, DEFAULT_PRISM);
    var prismMixBlendMode = resolveMixBlendMode(card.prism_mix_blend_mode, basicBackgrounds.prism_mix_blend_mode, DEFAULT_PRISM_MIX_BLEND_MODE);
    var cardEl = document.createElement("div");
    cardEl.className = "taro-card no-flip";
    cardEl.innerHTML =
      '<span class="taro-card-inner">' +
        '<span class="taro-card-face taro-card-back" style="background-image:url(\'' + safeUrl(resolvedBack) + '\')"><span class="taro-back-overlay"></span><span class="taro-prism"></span></span>' +
        '<span class="taro-card-face taro-card-front" style="background-image:url(\'' + safeUrl(resolvedFront) + '\')"><span class="taro-front-overlay"></span><span class="taro-prism"></span><span class="taro-swipe-indicator taro-swipe-yes">' + safe(labels.yes || "YES") + '</span><span class="taro-swipe-indicator taro-swipe-no">' + safe(labels.no || "NO") + '</span><span class="taro-content"><h3>' + safe(card.question_text || card.title || "") + '</h3><p>' + safe(card.sub_text || "") + '</p></span></span>' +
      '</span>';
    applyBackground(cardEl.querySelector(".taro-front-overlay"), frontOverlay);
    applyBackground(cardEl.querySelector(".taro-back-overlay"), backOverlay);
    cardEl.querySelectorAll(".taro-prism").forEach(function (prismEl) {
      applyPrismStyle(prismEl, prismBackground, prismMixBlendMode);
    });
    return cardEl;
  }

  function init(root) {
    var bootstrapConfig = {};
    try { bootstrapConfig = JSON.parse(root.getAttribute("data-bootstrap") || "{}"); } catch (e) {}
    var slugAlias = String(bootstrapConfig.slug_alias || root.getAttribute("data-slug") || "").trim();
    var storageSuffix = slugAlias || "default";
    var stateKey = "yesorno_state_" + storageSuffix;
    var cardsKey = "yesorno_cards_" + storageSuffix;
    var analyzingFrames = [
      messages.analyzing_1 || "Organizing your choice pattern...",
      messages.analyzing_2 || "Comparing your response flow...",
      messages.analyzing_3 || "Interpreting your result card...",
      messages.analyzing_4 || "Your result is ready."
    ];

    var context = {
      slug_alias: slugAlias,
      card_back_image_url: "",
      start_image_url: "",
      front_overlay_background: "",
      back_overlay_background: "",
      prism_background: "",
      prism_mix_blend_mode: "",
      display_count: 8
    };

    var basicBackgrounds = {
      front_overlay_background: resolveBackground("", context.front_overlay_background, DEFAULT_FRONT_OVERLAY),
      back_overlay_background: resolveBackground("", context.back_overlay_background, DEFAULT_BACK_OVERLAY),
      prism_background: resolveBackground("", context.prism_background, DEFAULT_PRISM),
      prism_mix_blend_mode: resolveMixBlendMode("", context.prism_mix_blend_mode, DEFAULT_PRISM_MIX_BLEND_MODE)
    };

    var els = {
      stageStart: root.querySelector(".taro-stage-start"),
      stageProgress: root.querySelector(".taro-stage-progress"),
      stageAnalyzing: root.querySelector(".taro-stage-analyzing"),
      stageComplete: root.querySelector(".taro-stage-complete"),
      start: root.querySelector(".taro-stage-start-button"),
      startCard: root.querySelector(".taro-start-card"),
      startCardFace: root.querySelector(".taro-start-card-face"),
      startIntro: root.querySelector(".taro-start-intro"),
      startTotal: root.querySelector(".taro-start-total"),
      startHint: root.querySelector(".taro-start-hint"),
      startRelief: root.querySelector(".taro-start-relief"),
      startLoading: null,
      startLoadingText: null,
      startLoadingFill: null,
      cards: root.querySelector(".taro-fortune-cards"),
      no: root.querySelector(".taro-answer-no"),
      yes: root.querySelector(".taro-answer-yes"),
      guide1: root.querySelector(".taro-progress-guide-1"),
      guide2: root.querySelector(".taro-progress-guide-2"),
      progressText: root.querySelector(".taro-progress-text"),
      progressFill: root.querySelector(".taro-progress-fill"),
      progressLabel: root.querySelector(".taro-progress-label"),
      analyzingText: root.querySelector(".taro-analyzing-text"),
      analyzingFill: root.querySelector(".taro-analyzing-fill"),
      completeText: root.querySelector(".taro-complete-text"),
      resultPreview: root.querySelector(".taro-result-preview"),
      resultFace: root.querySelector(".taro-result-card-face"),
      resultTitle: root.querySelector(".taro-result-title"),
      resultCtaText: root.querySelector(".taro-result-cta-text"),
      restartBtn: root.querySelector(".taro-restart-button")
    };

    els.start.textContent = labels.start || "Start";
    els.no.textContent = labels.no || "NO";
    els.yes.textContent = labels.yes || "YES";
    els.progressLabel.textContent = labels.progress || "Progress";
    els.restartBtn.textContent = labels.restart || "Restart";
    if (els.startIntro) els.startIntro.textContent = messages.start_intro || "Follow your first instinct.";
    if (els.startHint) els.startHint.textContent = messages.start_hint || "Do not overthink, pick the side that feels stronger.";
    if (els.startRelief) els.startRelief.textContent = messages.start_relief || "You can review your pattern in the result.";
    if (els.guide1) els.guide1.textContent = messages.guide_line_1 || "Swipe the card or use the buttons below.";
    if (els.guide2) els.guide2.textContent = messages.guide_line_2 || "Left means NO, right means YES.";

    function ensureStartLoadingUi() {
      var slot = root.querySelector(".taro-start-card-slot");
      if (!slot) return;
      var existing = slot.querySelector(".taro-start-loading");
      if (!existing) {
        var loading = document.createElement("div");
        loading.className = "taro-start-loading";
        loading.setAttribute("aria-live", "polite");
        loading.innerHTML = '<p class="taro-start-loading-text"></p><div class="taro-start-loading-bar"><span class="taro-start-loading-fill"></span></div>';
        slot.appendChild(loading);
        existing = loading;
      }
      els.startLoading = existing;
      els.startLoadingText = existing.querySelector(".taro-start-loading-text");
      els.startLoadingFill = existing.querySelector(".taro-start-loading-fill");
    }

    ensureStartLoadingUi();

    function renderStartCard() {
      var startImage = context.start_image_url || context.card_back_image_url || defaultBack || "";
      if (els.startCardFace && startImage) {
        els.startCardFace.style.backgroundImage = "url('" + safeUrl(startImage) + "')";
      }
      if (els.startCard) {
        if (window.gsap) {
          window.gsap.killTweensOf(els.startCard);
          window.gsap.set(els.startCard, { clearProps: "transform,opacity" });
        } else {
          els.startCard.style.transition = "";
          els.startCard.style.transform = "";
        }
        els.startCard.style.pointerEvents = "auto";
        els.startCard.style.opacity = "1";
      }
      if (els.startCardFace) {
        applyBackground(els.startCardFace.querySelector(".taro-front-overlay"), basicBackgrounds.front_overlay_background);
        els.startCardFace.querySelectorAll(".taro-prism").forEach(function (prismEl) {
          applyPrismStyle(prismEl, basicBackgrounds.prism_background, basicBackgrounds.prism_mix_blend_mode);
        });
        var startYesIndicator = els.startCardFace.querySelector(".taro-swipe-yes");
        var startNoIndicator = els.startCardFace.querySelector(".taro-swipe-no");
        if (startYesIndicator) startYesIndicator.textContent = labels.yes || "YES";
        if (startNoIndicator) startNoIndicator.textContent = labels.no || "NO";
      }
    }

    function hideStartCard() {
      if (!els.startCard) return;
      if (window.gsap) {
        window.gsap.killTweensOf(els.startCard);
        window.gsap.set(els.startCard, { clearProps: "transform", opacity: 0 });
      } else {
        els.startCard.style.transition = "";
        els.startCard.style.transform = "";
        els.startCard.style.opacity = "0";
      }
      els.startCard.style.pointerEvents = "none";
      els.startCard.classList.remove("show-yes", "show-no");
    }

    function applyBootstrapContext(json) {
      context.slug_alias = String((json && json.slug_alias) || context.slug_alias || "").trim();
      context.card_back_image_url = String((json && json.card_back_image_url) || "");
      context.start_image_url = String((json && json.start_image_url) || context.card_back_image_url || "");
      context.front_overlay_background = String((json && json.front_overlay_background) || "");
      context.back_overlay_background = String((json && json.back_overlay_background) || "");
      context.prism_background = String((json && json.prism_background) || "");
      context.prism_mix_blend_mode = String((json && json.prism_mix_blend_mode) || "");
      context.display_count = Math.max(8, Math.min(10, Number((json && json.display_count) || context.display_count || 8)));

      basicBackgrounds.front_overlay_background = resolveBackground("", context.front_overlay_background, DEFAULT_FRONT_OVERLAY);
      basicBackgrounds.back_overlay_background = resolveBackground("", context.back_overlay_background, DEFAULT_BACK_OVERLAY);
      basicBackgrounds.prism_background = resolveBackground("", context.prism_background, DEFAULT_PRISM);
      basicBackgrounds.prism_mix_blend_mode = resolveMixBlendMode("", context.prism_mix_blend_mode, DEFAULT_PRISM_MIX_BLEND_MODE);
    }

    var state = {
      ids: [],
      answers: [],
      idx: 0,
      status: "start",
      session_token: "",
      analyzingStartedAt: 0,
      analyzingDuration: 0,
      finalResult: null
    };
    var busy = false;
    var analyzingTimer = null;
    var resultRequestPromise = null;

    var sessionCards = [];
    var sessionLoaded = false;
    var bootstrapLoaded = false;
    var bootstrapPromise = null;
    var bootstrapLoadingTimer = null;
    var bootstrapLoadingProgress = 0;
    var bootstrapLoadingStartedAt = 0;
    var bootstrapLoadingMinDuration = 900;
    var startHintTimers = [];
    var startHintTimeline = null;

    function saveState() { sessionStorage.setItem(stateKey, JSON.stringify(state)); }
    function saveCards(cards) {
      sessionStorage.setItem(cardsKey, JSON.stringify(Array.isArray(cards) ? cards : []));
    }
    function loadState() {
      try {
        var s = JSON.parse(sessionStorage.getItem(stateKey) || "null");
        if (s && Array.isArray(s.ids)) {
          state = Object.assign(state, s);
          return true;
        }
      } catch (e) {}
      return false;
    }
    function loadCards() {
      try {
        var cards = JSON.parse(sessionStorage.getItem(cardsKey) || "[]");
        sessionCards = Array.isArray(cards) ? cards.slice() : [];
      } catch (e) {
        sessionCards = [];
      }
      sessionLoaded = sessionCards.length > 0;
      return sessionCards;
    }

    function show(stage) {
      [els.stageStart, els.stageProgress, els.stageAnalyzing, els.stageComplete].forEach(function (el) {
        if (el) el.classList.remove("is-active");
      });
      if (stage === "start" && els.stageStart) els.stageStart.classList.add("is-active");
      if (stage === "progress" && els.stageProgress) els.stageProgress.classList.add("is-active");
      if (stage === "analyzing" && els.stageAnalyzing) els.stageAnalyzing.classList.add("is-active");
      if (stage === "complete" && els.stageComplete) els.stageComplete.classList.add("is-active");
    }

    function progress() {
      var total = state.ids.length;
      if (els.startTotal) {
        els.startTotal.textContent = messages.start_total || ("Total " + total + " questions");
      }
      var cur = state.status === "complete" ? total : Math.min(state.idx + 1, total);
      els.progressText.textContent = cur + "/" + total;
      els.progressFill.style.width = (total ? Math.round((cur / total) * 100) : 0) + "%";
    }

    function getCard(id) {
      for (var i = 0; i < sessionCards.length; i += 1) {
        if (sessionCards[i].id === id) return sessionCards[i];
      }
      return null;
    }

    function postAction(action, payload) {
      var form = new URLSearchParams();
      form.append("action", String(action || ""));
      Object.keys(payload || {}).forEach(function (key) {
        form.append(key, String(payload[key] || ""));
      });

      return fetch(apiPostUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: form.toString()
      }).then(function (res) {
        return res.json().catch(function () {
          var parseErr = new Error("Invalid JSON response");
          parseErr.status = res.status;
          throw parseErr;
        }).then(function (json) {
          if (!res.ok || !json || json.success !== true) {
            var message = json && json.data && json.data.message ? String(json.data.message) : ("HTTP " + res.status);
            var err = new Error(message);
            err.status = res.status;
            err.code = json && json.data && json.data.code ? String(json.data.code) : "";
            throw err;
          }
          return json.data || {};
        });
      });
    }

    function requestBootstrap(forceRefresh) {
      var force = !!forceRefresh;
      if (!context.slug_alias) {
        return Promise.reject(new Error("Missing test identity"));
      }
      if (force) {
        bootstrapLoaded = false;
      }
      if (bootstrapLoaded && sessionLoaded && state.session_token) {
        return Promise.resolve(sessionCards);
      }
      if (!force && bootstrapPromise) {
        return bootstrapPromise;
      }

      bootstrapPromise = Promise.resolve().then(function () {
        return postAction("yesorno_bootstrap", { slug: context.slug_alias }).then(function (json) {
          applyBootstrapContext(json || {});
          sessionCards = Array.isArray(json.cards) ? json.cards.slice() : [];
          sessionLoaded = sessionCards.length > 0;
          state.session_token = json && json.session_token ? String(json.session_token) : "";
          saveCards(sessionCards);
          saveState();
          bootstrapLoaded = true;
          return sessionCards;
        });
      }).catch(function (err) {
        if (window.console && typeof window.console.error === "function") {
          window.console.error("[YesOrNo] Failed to bootstrap test", {
            status: err && typeof err.status !== "undefined" ? err.status : null,
            slug: context.slug_alias || "",
            action: "yesorno_bootstrap",
            code: err && err.code ? err.code : ""
          });
        }
        throw err;
      }).finally(function () {
        bootstrapPromise = null;
      });
      return bootstrapPromise;
    }

    function requestResult() {
      if (!context.slug_alias || !state.session_token) {
        return Promise.resolve(null);
      }

      return postAction("yesorno_result", {
        slug: context.slug_alias,
        token: String(state.session_token),
        answers: JSON.stringify(Array.isArray(state.answers) ? state.answers : [])
      }).then(function (json) {
        state.finalResult = json && json.result ? json.result : null;
        saveState();
        return state.finalResult;
      }).catch(function () {
        state.finalResult = null;
        saveState();
        return null;
      });
    }

    function clearAnalyzingTimer() {
      if (analyzingTimer) {
        clearInterval(analyzingTimer);
        analyzingTimer = null;
      }
    }

    function setBootstrapLoading(active, done, onHidden) {
      if (!els.startLoading || !els.startLoadingFill) {
        if (!active && done && typeof onHidden === "function") onHidden();
        return;
      }
      if (bootstrapLoadingTimer) {
        clearInterval(bootstrapLoadingTimer);
        bootstrapLoadingTimer = null;
      }

      if (active) {
        bootstrapLoadingStartedAt = Date.now();
        bootstrapLoadingProgress = Math.max(12, bootstrapLoadingProgress || 12);
        if (els.startLoadingText) {
          els.startLoadingText.textContent = messages.analyzing_1 || "Preparing your cards...";
        }
        els.startLoading.classList.add("is-visible");
        els.startLoadingFill.style.width = bootstrapLoadingProgress + "%";
        bootstrapLoadingTimer = setInterval(function () {
          bootstrapLoadingProgress = Math.min(86, bootstrapLoadingProgress + Math.floor(Math.random() * 8 + 2));
          els.startLoadingFill.style.width = bootstrapLoadingProgress + "%";
        }, 180);
        return;
      }

      if (done) {
        var elapsed = bootstrapLoadingStartedAt ? (Date.now() - bootstrapLoadingStartedAt) : bootstrapLoadingMinDuration;
        var waitMs = Math.max(0, bootstrapLoadingMinDuration - elapsed);
        if (els.startLoadingText) {
          els.startLoadingText.textContent = messages.analyzing_4 || "Your result is ready.";
        }
        els.startLoadingFill.style.width = "100%";
        setTimeout(function () {
          setTimeout(function () {
            els.startLoading.classList.remove("is-visible");
            if (els.startLoadingText) els.startLoadingText.textContent = "";
            els.startLoadingFill.style.width = "0%";
            bootstrapLoadingProgress = 0;
            bootstrapLoadingStartedAt = 0;
            if (typeof onHidden === "function") onHidden();
          }, 220);
        }, waitMs);
        return;
      }

      els.startLoading.classList.remove("is-visible");
      if (els.startLoadingText) els.startLoadingText.textContent = "";
      els.startLoadingFill.style.width = "0%";
      bootstrapLoadingProgress = 0;
      bootstrapLoadingStartedAt = 0;
    }

    function enterStartLoading(forceRefresh) {
      stopStartCardHintAnimation(true);
      clearAnalyzingTimer();
      show("start");
      if (els.start) {
        els.start.disabled = true;
      }
      hideStartCard();
      setBootstrapLoading(true, false);

      return requestBootstrap(!!forceRefresh).then(function (cards) {
        if (!state.ids.length && els.startTotal) {
          var preview = Array.isArray(cards) && cards.length ? cards.length : context.display_count;
          els.startTotal.textContent = messages.start_total || ("Total " + preview + " questions");
        }
        return new Promise(function (resolve) {
          setBootstrapLoading(false, true, function () {
            if (els.start) {
              els.start.disabled = false;
            }
            renderStartCard();
            animateStartCardHint();
            resolve(cards);
          });
        });
      }).catch(function (err) {
        setBootstrapLoading(false, false);
        if (els.start) {
          els.start.disabled = false;
        }
        renderStartCard();
        throw err;
      });
    }

    function clearStartHintTimers() {
      while (startHintTimers.length) {
        clearTimeout(startHintTimers.pop());
      }
    }

    function stopStartCardHintAnimation(resetCard) {
      clearStartHintTimers();
      if (startHintTimeline && typeof startHintTimeline.kill === "function") {
        startHintTimeline.kill();
      }
      startHintTimeline = null;

      if (!els.startCard) return;
      els.startCard.classList.remove("show-yes", "show-no");
      if (!resetCard) return;

      if (window.gsap) {
        window.gsap.killTweensOf(els.startCard);
        window.gsap.set(els.startCard, { clearProps: "transform,opacity" });
      } else {
        els.startCard.style.transition = "";
        els.startCard.style.transform = "";
        els.startCard.style.opacity = "1";
      }
    }

    function animateStartCardHint() {
      if (!els.startCard || state.status !== "start") return;
      stopStartCardHintAnimation(true);

      var offset = 26;
      var cycles = 2;
      if (window.gsap) {
        startHintTimeline = window.gsap.timeline();
        for (var cycle = 0; cycle < cycles; cycle += 1) {
          startHintTimeline
            .to(els.startCard, { x: -offset, rotation: -6, opacity: 0.92, duration: 0.2, ease: "power1.out", onStart: function () {
              els.startCard.classList.add("show-no");
              els.startCard.classList.remove("show-yes");
            }})
            .to(els.startCard, { x: offset, rotation: 6, opacity: 0.92, duration: 0.24, ease: "power1.inOut", onStart: function () {
              els.startCard.classList.add("show-yes");
              els.startCard.classList.remove("show-no");
            }})
            .to(els.startCard, { x: 0, rotation: 0, opacity: 1, duration: 0.18, ease: "power1.out", onStart: function () {
              els.startCard.classList.remove("show-yes", "show-no");
            }});
        }
        startHintTimeline.eventCallback("onComplete", function () {
          if (window.gsap) window.gsap.set(els.startCard, { clearProps: "transform,opacity" });
          startHintTimeline = null;
        });
        return;
      }

      function runHintCycle(cycleIndex) {
        if (state.status !== "start" || cycleIndex >= cycles) {
          els.startCard.style.transition = "";
          return;
        }

        els.startCard.style.transition = "transform .2s ease, opacity .2s ease";
        els.startCard.style.transform = "translateX(" + (-offset) + "px) rotate(-6deg)";
        els.startCard.style.opacity = "0.92";
        els.startCard.classList.add("show-no");
        els.startCard.classList.remove("show-yes");

        startHintTimers.push(setTimeout(function () {
          if (state.status !== "start") return;
          els.startCard.style.transition = "transform .24s ease, opacity .24s ease";
          els.startCard.style.transform = "translateX(" + offset + "px) rotate(6deg)";
          els.startCard.style.opacity = "0.92";
          els.startCard.classList.add("show-yes");
          els.startCard.classList.remove("show-no");
        }, 210));

        startHintTimers.push(setTimeout(function () {
          if (state.status !== "start") return;
          els.startCard.style.transition = "transform .18s ease, opacity .18s ease";
          els.startCard.style.transform = "";
          els.startCard.style.opacity = "1";
          els.startCard.classList.remove("show-yes", "show-no");
        }, 470));

        startHintTimers.push(setTimeout(function () {
          runHintCycle(cycleIndex + 1);
        }, 730));
      }

      runHintCycle(0);
    }

    function resolveResult() {
      if (state.finalResult) return state.finalResult;
      return {
        label: labels.completed || "Your result is ready.",
        result_summary: messages.result_summary_fallback || "We prepared a short summary from your answer flow.",
        result_image_url: context.card_back_image_url || defaultBack || "",
        result_cta_label: "View Details",
        result_url: ""
      };
    }

    function renderResultView() {
      var result = resolveResult();
      if (els.resultFace && result.result_image_url) {
        els.resultFace.style.backgroundImage = "url('" + safeUrl(result.result_image_url) + "')";
      }
      if (els.resultFace) {
        applyBackground(els.resultFace.querySelector(".taro-front-overlay"), basicBackgrounds.front_overlay_background);
        els.resultFace.querySelectorAll(".taro-prism").forEach(function (prismEl) {
          applyPrismStyle(prismEl, basicBackgrounds.prism_background, basicBackgrounds.prism_mix_blend_mode);
        });
      }
      if (els.resultTitle) els.resultTitle.textContent = result.label || (labels.completed || "Your result is ready.");
      if (els.resultCtaText) els.resultCtaText.textContent = result.result_cta_label || "View Details";
      if (els.completeText) els.completeText.textContent = result.result_summary || (messages.result_summary_fallback || "We prepared a short summary from your answer flow.");
      if (els.resultPreview) {
        if (result.result_url) {
          els.resultPreview.classList.remove("is-disabled");
          els.resultPreview.removeAttribute("aria-disabled");
        } else {
          els.resultPreview.classList.add("is-disabled");
          els.resultPreview.setAttribute("aria-disabled", "true");
        }
      }
    }

    function completeFromAnalyzing() {
      clearAnalyzingTimer();
      var finalize = function () {
        state.status = "complete";
        saveState();
        progress();
        renderResultView();
        show("complete");
      };

      if (resultRequestPromise) {
        resultRequestPromise.finally(function () {
          resultRequestPromise = null;
          finalize();
        });
        return;
      }
      finalize();
    }

    function updateAnalyzing() {
      var now = Date.now();
      var start = Number(state.analyzingStartedAt || now);
      var duration = Number(state.analyzingDuration || 2600);
      var elapsed = Math.max(0, now - start);
      var ratio = Math.max(0, Math.min(1, elapsed / duration));
      var idx = Math.min(analyzingFrames.length - 1, Math.floor(ratio * analyzingFrames.length));
      if (els.analyzingText) els.analyzingText.textContent = analyzingFrames[idx];
      if (els.analyzingFill) els.analyzingFill.style.width = Math.round(ratio * 100) + "%";
      if (elapsed >= duration) completeFromAnalyzing();
    }

    function beginAnalyzing() {
      clearAnalyzingTimer();
      state.status = "analyzing";
      state.analyzingStartedAt = Date.now();
      state.analyzingDuration = 2200 + Math.floor(Math.random() * 1001);
      resultRequestPromise = requestResult();
      saveState();
      show("analyzing");
      updateAnalyzing();
      analyzingTimer = setInterval(updateAnalyzing, 120);
    }

    function pulseAnswerButton(btn) {
      if (!btn) return;
      btn.classList.add("is-pressed");
      setTimeout(function () { btn.classList.remove("is-pressed"); }, 180);
    }

    function next(answer, dragState) {
      if (busy) return;
      var card = getCard(state.ids[state.idx]);
      var cardEl = els.cards.querySelector(".taro-card");
      if (!card || !cardEl) return;
      busy = true;
      pulseAnswerButton(answer === "yes" ? els.yes : els.no);

      state.answers.push({
        card_id: card.id,
        answer: answer === "yes" ? "yes" : "no"
      });

      var finish = function () {
        state.idx += 1;
        if (state.idx >= state.ids.length) {
          saveState();
          progress();
          busy = false;
          beginAnalyzing();
          return;
        }
        saveState();
        progress();
        renderCard();
        busy = false;
      };

      var exitDuration = state.idx >= state.ids.length - 1 ? 0.34 : 0.24;
      if (window.gsap) {
        if (dragState && typeof dragState.x === "number") {
          window.gsap.set(cardEl, { x: dragState.x, rotation: Number(dragState.rotation || 0) });
          if (typeof dragState.opacity === "number") window.gsap.set(cardEl, { opacity: dragState.opacity });
        }
        window.gsap.to(cardEl, {
          x: answer === "yes" ? 240 : -240,
          rotation: answer === "yes" ? 12 : -12,
          opacity: 0,
          duration: exitDuration,
          ease: "power2.in",
          onComplete: finish
        });
      } else {
        finish();
      }
    }

    function attachDrag(cardEl) {
      var startX = 0;
      var dragging = false;
      var currentX = 0;
      var threshold = 56;
      cardEl.style.touchAction = "none";

      cardEl.addEventListener("pointerdown", function (e) {
        if (busy) return;
        if (window.gsap) window.gsap.killTweensOf(cardEl);
        dragging = true;
        startX = e.clientX;
        currentX = 0;
        cardEl.style.opacity = "1";
        cardEl.classList.remove("show-yes", "show-no");
        cardEl.setPointerCapture(e.pointerId);
      });

      cardEl.addEventListener("pointermove", function (e) {
        if (!dragging || busy) return;
        currentX = e.clientX - startX;
        var fade = Math.max(0.35, 1 - Math.abs(currentX) / 520);
        cardEl.style.transform = "translateX(" + currentX + "px) rotate(" + (currentX * 0.05) + "deg)";
        cardEl.style.opacity = String(fade);
        cardEl.classList.toggle("show-yes", currentX >= threshold);
        cardEl.classList.toggle("show-no", currentX <= -threshold);
      });

      function endDrag(e) {
        if (!dragging || busy) return;
        dragging = false;
        if (e && typeof e.pointerId !== "undefined" && cardEl.hasPointerCapture(e.pointerId)) {
          cardEl.releasePointerCapture(e.pointerId);
        }
        if (Math.abs(currentX) >= threshold) {
          next(currentX > 0 ? "yes" : "no", {
            x: currentX,
            rotation: currentX * 0.05,
            opacity: Math.max(0.35, 1 - Math.abs(currentX) / 520)
          });
        } else {
          cardEl.classList.remove("show-yes", "show-no");
          if (window.gsap) {
            window.gsap.to(cardEl, { x: 0, rotation: 0, opacity: 1, duration: 0.16, ease: "power2.out", clearProps: "transform,opacity" });
          } else {
            cardEl.style.transform = "";
            cardEl.style.opacity = "1";
          }
        }
      }

      cardEl.addEventListener("pointerup", endDrag);
      cardEl.addEventListener("pointercancel", endDrag);
    }

    function renderCard() {
      els.cards.innerHTML = "";
      var card = getCard(state.ids[state.idx]);
      if (!card) return;
      var cardEl = cardElement(card, context.card_back_image_url || defaultBack, basicBackgrounds);
      els.cards.appendChild(cardEl);
      if (window.gsap) window.gsap.from(cardEl, { opacity: 0, y: 12, duration: 0.2, ease: "power2.out" });
      attachDrag(cardEl);
    }

    function startFlow() {
      if (busy || state.status === "progress" || state.status === "analyzing") return;
      if (!sessionLoaded || !sessionCards.length || !state.session_token) {
        enterStartLoading(true).catch(function () {});
        return;
      }
      busy = true;
      stopStartCardHintAnimation(true);
      clearAnalyzingTimer();
      var cards = sessionCards.slice();
      var sessionToken = state.session_token;
      state = {
        ids: cards.map(function (c) { return c.id; }),
        answers: [],
        idx: 0,
        status: "progress",
        session_token: sessionToken,
        analyzingStartedAt: 0,
        analyzingDuration: 0,
        finalResult: null
      };
      saveCards(cards);
      saveState();
      progress();
      show("progress");
      renderCard();
      busy = false;
    }

    function openResultLink() {
      var result = resolveResult();
      if (!result.result_url) {
        alert(labels.result_missing || "Result link is not configured.");
        return;
      }
      window.location.href = result.result_url;
    }

    els.start.addEventListener("click", startFlow);
    if (els.startCard) {
      var startDrag = {
        active: false,
        pointerId: null,
        startX: 0,
        deltaX: 0,
        threshold: 40,
        indicatorThreshold: 18,
        suppressClick: false
      };
      els.startCard.style.touchAction = "none";

      els.startCard.addEventListener("pointerdown", function (e) {
        if (busy || state.status !== "start") return;
        stopStartCardHintAnimation(false);
        if (window.gsap) window.gsap.killTweensOf(els.startCard);
        startDrag.active = true;
        startDrag.pointerId = e.pointerId;
        startDrag.startX = e.clientX;
        startDrag.deltaX = 0;
        startDrag.suppressClick = false;
        els.startCard.style.transition = "";
        els.startCard.style.opacity = "1";
        els.startCard.classList.remove("show-yes", "show-no");
        if (typeof els.startCard.setPointerCapture === "function") {
          els.startCard.setPointerCapture(e.pointerId);
        }
      });

      els.startCard.addEventListener("pointermove", function (e) {
        if (!startDrag.active || startDrag.pointerId !== e.pointerId) return;
        startDrag.deltaX = e.clientX - startDrag.startX;
        var rotate = startDrag.deltaX * 0.05;
        var fade = Math.max(0.35, 1 - Math.abs(startDrag.deltaX) / 560);
        els.startCard.style.transform = "translateX(" + startDrag.deltaX + "px) rotate(" + rotate + "deg)";
        els.startCard.style.opacity = String(fade);
        els.startCard.classList.toggle("show-yes", startDrag.deltaX >= startDrag.indicatorThreshold);
        els.startCard.classList.toggle("show-no", startDrag.deltaX <= -startDrag.indicatorThreshold);
      });

      function resetStartCardPosition() {
        if (window.gsap) {
          window.gsap.to(els.startCard, {
            x: 0,
            rotation: 0,
            opacity: 1,
            duration: 0.18,
            ease: "power2.out",
            clearProps: "transform,opacity"
          });
        } else {
          els.startCard.style.transition = "transform .18s ease, opacity .18s ease";
          els.startCard.style.transform = "";
          els.startCard.style.opacity = "1";
          setTimeout(function () { els.startCard.style.transition = ""; }, 220);
        }
        els.startCard.classList.remove("show-yes", "show-no");
      }

      function flyOutStartCardAndStart(deltaX) {
        var direction = Number(deltaX || 0) >= 0 ? 1 : -1;
        var targetX = direction * 280;
        if (window.gsap) {
          window.gsap.to(els.startCard, {
            x: targetX,
            rotation: direction * 12,
            opacity: 0,
            duration: 0.22,
            ease: "power2.in",
            onComplete: startFlow
          });
        } else {
          els.startCard.style.transition = "transform .22s ease, opacity .22s ease";
          els.startCard.style.transform = "translateX(" + targetX + "px) rotate(" + (direction * 12) + "deg)";
          els.startCard.style.opacity = "0";
          setTimeout(startFlow, 230);
        }
        els.startCard.classList.remove("show-yes", "show-no");
      }

      function endStartCardDrag(e) {
        if (!startDrag.active || startDrag.pointerId !== e.pointerId) return;
        var finalDeltaX = startDrag.deltaX;
        var movedX = Math.abs(startDrag.deltaX);
        startDrag.active = false;
        startDrag.pointerId = null;
        startDrag.deltaX = 0;
        if (typeof els.startCard.hasPointerCapture === "function" && els.startCard.hasPointerCapture(e.pointerId)) {
          els.startCard.releasePointerCapture(e.pointerId);
        }
        if (movedX >= startDrag.threshold) {
          startDrag.suppressClick = true;
          flyOutStartCardAndStart(finalDeltaX);
        } else {
          resetStartCardPosition();
        }
      }

      els.startCard.addEventListener("pointerup", endStartCardDrag);
      els.startCard.addEventListener("pointercancel", endStartCardDrag);
      els.startCard.addEventListener("click", function () {
        if (startDrag.suppressClick) {
          startDrag.suppressClick = false;
          return;
        }
        startFlow();
      });
      els.startCard.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          startFlow();
        }
      });
    }

    els.no.addEventListener("click", function () { next("no"); });
    els.yes.addEventListener("click", function () { next("yes"); });

    if (els.resultPreview) {
      els.resultPreview.addEventListener("click", openResultLink);
      els.resultPreview.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openResultLink();
        }
      });
    }

    els.restartBtn.addEventListener("click", function () {
      stopStartCardHintAnimation(true);
      clearAnalyzingTimer();
      sessionStorage.removeItem(stateKey);
      sessionStorage.removeItem(cardsKey);
      sessionCards = [];
      sessionLoaded = false;
      bootstrapLoaded = false;
      state = {
        ids: [],
        answers: [],
        idx: 0,
        status: "start",
        session_token: "",
        analyzingStartedAt: 0,
        analyzingDuration: 0,
        finalResult: null
      };
      resultRequestPromise = null;
      show("start");
      progress();
      els.cards.innerHTML = "";
      enterStartLoading(true).catch(function () {});
    });

    var hasState = loadState();
    if (hasState) {
      loadCards();
    }

    if (hasState && state.status === "progress" && state.ids.length && sessionCards.length) {
      stopStartCardHintAnimation(true);
      show("progress");
      progress();
      renderCard();
    } else if (hasState && state.status === "analyzing" && state.ids.length) {
      stopStartCardHintAnimation(true);
      show("analyzing");
      progress();
      resultRequestPromise = requestResult();
      updateAnalyzing();
      analyzingTimer = setInterval(updateAnalyzing, 120);
    } else if (hasState && state.status === "complete") {
      stopStartCardHintAnimation(true);
      show("complete");
      progress();
      renderResultView();
    } else {
      show("start");
      els.progressText.textContent = "0/0";
      els.progressFill.style.width = "0%";
      if (els.startTotal) {
        var previewCount = Math.max(8, Math.min(10, Number(context.display_count || 8)));
        els.startTotal.textContent = messages.start_total || ("Total " + previewCount + " questions");
      }
      enterStartLoading(false).catch(function () {});
    }
  }

  document.querySelectorAll(".taro-fortune-root").forEach(init);
})();
