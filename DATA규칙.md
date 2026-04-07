# YesOrNo JSON DATA 규칙

## 1) 규칙 및 옵션 설명

### 최상위 구조
- JSON import는 아래 3개 섹션이 반드시 있어야 합니다.
  - `basic` (object)
  - `cards` (array)
  - `results` (array)

### basic
- `title` (string, 사실상 필수)
  - 비어 있으면 저장/정규화 실패.
- `slug_alias` (string)
  - 비어 있으면 자동 생성.
- `id` (string)
  - 비어 있으면 자동 생성.
- `description` (string)
- `card_back_image_url` (string URL)
- `start_image_url` (string URL)
- `front_overlay_background` (string)
- `back_overlay_background` (string)
- `prism_background` (string)
- `prism_mix_blend_mode` (string)
  - 허용값: `screen`, `normal`, `multiply`, `overlay`, `soft-light`, `hard-light`, `color-dodge`, `lighten`
  - 허용값 외 입력 시 기본값 처리.
- `display_count` (int)
  - 저장 시 `8 ~ 10`으로 보정.
- `pool_count` (int)
  - 저장 시 `16 ~ 24`으로 보정.
- `groups_quota` (object)
  - 예: `{ "A": 2, "B": 2 }`
  - 값은 1 이상의 정수만 유효.
- `active` (0/1)

### cards[] 각 항목
- `question_text` (string, 필수급)
  - 비어 있으면 `description`을 대체 사용.
  - 둘 다 비어 있으면 해당 카드는 제거됨.
- `id` (string)
  - 비어 있으면 자동 생성.
- `image_url` (string URL)
- `sub_text` (string)
- `group` (string)
- `front_overlay_background` (string)
- `back_overlay_background` (string)
- `prism_background` (string)
- `prism_mix_blend_mode` (string, basic과 동일 허용값 규칙)
- `score_yes` (object)
  - 예: `{ "cat_a": 2, "cat_b": 0 }`
- `score_no` (object)
- `active` (0/1)

### results[] 각 항목
- `result_code` (string, 필수)
- `label` (string, 필수)
- `post_id` (int)
- `result_url` (string URL)
  - `post_id`가 있으면 permalink 우선.
- `result_image_url` (string URL)
- `result_summary` (string)
- `result_cta_label` (string)
  - 비면 기본 `"View Details"`.
- `active` (0/1)

### 결과 계산/운영 규칙
- import 시 `results`는 최소 1개 필요.
- import 시 `active=1` 결과도 최소 1개 필요.
- 런타임 결과 계산은 서버에서 `answers`와 카드 `score_yes/score_no` 누적으로 처리.

---

## 2) 누락가능 기본형

아래는 import 가능한 최소 형태(필수 최소값 중심)입니다.

```json
{
  "basic": {
    "title": "샘플 테스트"
  },
  "cards": [
    {
      "question_text": "첫 질문",
      "score_yes": { "default": 1 },
      "score_no": { "default": 0 },
      "active": 1
    }
  ],
  "results": [
    {
      "result_code": "default",
      "label": "기본 결과",
      "active": 1
    }
  ]
}
```

---

## 3) 전체 입력형

```json
{
  "basic": {
    "id": "love-type-001",
    "slug_alias": "love-type",
    "title": "연애 성향 테스트",
    "description": "질문 흐름 기반 성향 분석",
    "card_back_image_url": "https://example.com/back.jpg",
    "start_image_url": "https://example.com/start.jpg",
    "front_overlay_background": "linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))",
    "back_overlay_background": "linear-gradient(185deg, rgb(93 162 255 / 12%), rgb(18 9 11 / 72%))",
    "prism_background": "linear-gradient(110deg, rgba(255,255,255,0.05) 18%, rgba(255,107,129,0.3) 32%, rgba(92,245,255,0.26) 48%, rgba(255,201,107,0.34) 64%, rgba(255,255,255,0.05) 78%)",
    "prism_mix_blend_mode": "screen",
    "display_count": 8,
    "pool_count": 16,
    "groups_quota": {
      "A": 2,
      "B": 2,
      "C": 2,
      "D": 1,
      "E": 1
    },
    "active": 1
  },
  "cards": [
    {
      "id": "q1",
      "image_url": "https://example.com/q1.jpg",
      "question_text": "상대가 늦으면 기다릴 수 있나?",
      "sub_text": "첫 반응으로 선택",
      "group": "A",
      "front_overlay_background": "",
      "back_overlay_background": "",
      "prism_background": "",
      "prism_mix_blend_mode": "screen",
      "score_yes": { "warm": 2, "cool": 0 },
      "score_no": { "warm": 0, "cool": 2 },
      "active": 1
    }
  ],
  "results": [
    {
      "result_code": "warm",
      "label": "따뜻한 공감형",
      "post_id": 0,
      "result_url": "https://example.com/result/warm",
      "result_image_url": "https://example.com/result-warm.jpg",
      "result_summary": "정서 공감이 강한 타입",
      "result_cta_label": "자세히 보기",
      "active": 1
    },
    {
      "result_code": "cool",
      "label": "이성적 안정형",
      "post_id": 0,
      "result_url": "https://example.com/result/cool",
      "result_image_url": "https://example.com/result-cool.jpg",
      "result_summary": "판단 중심의 균형 타입",
      "result_cta_label": "자세히 보기",
      "active": 1
    }
  ]
}
```

---

## 4) 참고사항

- `display_count`는 최소 8이므로, 운영 시 활성 카드 수를 8개 이상 권장.
- `groups_quota` 합이 `display_count`보다 작으면 남은 수량은 랜덤 보충.
- `score_yes/score_no`의 키는 `results[].result_code`와 맞춰야 계산이 정확함.
- `active=0` 카드/결과는 계산 대상에서 제외될 수 있음.
- 입력값은 저장 시 sanitize/정규화되므로, 원본과 저장본이 다를 수 있음.

---

## 5) AI 제작시 주의사항

- 반드시 `basic/cards/results` 3섹션을 모두 생성.
- `basic.title`은 비우지 말 것.
- `results`는 최소 1개 이상, 그중 `active=1` 최소 1개 이상 보장.
- 카드마다 `question_text`를 넣고, `score_yes/score_no` 키를 결과 코드와 일치시킬 것.
- `display_count`보다 활성 카드 수가 적은 JSON을 만들지 말 것.
- `prism_mix_blend_mode`는 허용값만 사용.
- URL 필드는 실제 `http/https` 형태로 생성.

---

## 6) 실패 예시(JSON 오류 케이스)

### 케이스 A: 필수 섹션 누락 (`basic/cards/results`)

```json
{
  "basic": {
    "title": "테스트"
  },
  "cards": []
}
```

- 문제: `results` 섹션이 없음.
- 결과: import 실패 (`missing_sections`).

### 케이스 B: `basic.title` 비어 있음

```json
{
  "basic": {
    "title": ""
  },
  "cards": [
    { "question_text": "Q1", "active": 1 }
  ],
  "results": [
    { "result_code": "default", "label": "기본", "active": 1 }
  ]
}
```

- 문제: 타이틀 필수값 누락.
- 결과: payload 정규화 실패 (`invalid_payload` 계열).

### 케이스 C: 결과가 0개 또는 활성 결과가 없음

```json
{
  "basic": { "title": "테스트" },
  "cards": [
    { "question_text": "Q1", "active": 1 }
  ],
  "results": [
    { "result_code": "r1", "label": "결과1", "active": 0 }
  ]
}
```

- 문제: `active=1` 결과가 없음.
- 결과: import 실패 (`active_result_required`).

### 케이스 D: 카드 질문 텍스트 누락

```json
{
  "basic": { "title": "테스트" },
  "cards": [
    { "id": "q1", "active": 1 }
  ],
  "results": [
    { "result_code": "default", "label": "기본", "active": 1 }
  ]
}
```

- 문제: 카드 `question_text`(또는 `description`) 없음.
- 결과: 해당 카드가 정규화 과정에서 제거됨.
- 부작용: 활성 카드 수 부족 시 런타임에서 문항 구성 실패 가능.

### 케이스 E: 스코어 키와 결과 코드 불일치

```json
{
  "basic": { "title": "테스트" },
  "cards": [
    {
      "question_text": "Q1",
      "score_yes": { "x": 2 },
      "score_no": { "y": 1 },
      "active": 1
    }
  ],
  "results": [
    { "result_code": "a", "label": "A", "active": 1 },
    { "result_code": "b", "label": "B", "active": 1 }
  ]
}
```

- 문제: 카드 점수 키(`x`,`y`)가 결과 코드(`a`,`b`)와 매칭되지 않음.
- 결과: 계산 점수가 의도와 다르게 누적되거나 기본 우선순위 결과로 치우침.

### 케이스 F: `display_count` 대비 활성 카드 수 부족

```json
{
  "basic": {
    "title": "테스트",
    "display_count": 10
  },
  "cards": [
    { "question_text": "Q1", "active": 1 },
    { "question_text": "Q2", "active": 1 }
  ],
  "results": [
    { "result_code": "default", "label": "기본", "active": 1 }
  ]
}
```

- 문제: 활성 카드가 너무 적음.
- 결과: 표시 카드 구성이 비정상/부족해질 수 있으므로 운영 실패 위험 큼.
