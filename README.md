# YesOrNo WordPress Plugin

카드형 인터랙션 UI를 유지하면서 YES/NO 테스트 엔진으로 확장한 워드프레스 플러그인입니다.

## 1) 프로젝트 개요

- 플러그인명: `YesOrNo`
- 현재 버전: `1.2.0` (`taro-fortune.php` 기준)
- 핵심 숏코드: `[yesorno yesorno="slug"]`
- 목적:
  - 기존 카드 비주얼/애니메이션 톤 유지
  - 단순 카드 표시가 아닌 카드 기반 테스트 진행/결과 이동 제공

---

## 2) 개발 기능 정리 (순서별)

아래는 지금까지 반영된 기능을 구현 흐름 순서대로 정리한 내용입니다.

### Phase 1. 구조 전환 (운세 카드 -> 테스트 엔진)

1. `YesOrNo` 기준 부트스트랩 구조 정리
- 플러그인 엔트리/클래스 로딩 구조를 YesOrNo 네이밍으로 운영
- 프론트/관리자/데이터 스토어 분리 유지

2. 숏코드 표준화
- 메인 숏코드: `[yesorno yesorno="slug"]`
- slug 기준 테스트 조회 렌더

3. 데이터 저장 구조 고도화
- `yesorno_test` CPT + post meta 기반 저장
- 테스트 기본정보 / 카드 목록 / 결과 목록을 메타로 분리 저장

### Phase 2. 프론트 테스트 플로우 구현

4. 3단계 화면 흐름
- 시작 화면 -> 카드 진행 -> 완료 화면

5. 그룹 쿼터 랜덤 카드 추출
- `groups_quota` 기준 그룹별 카드 선발
- `display_count` 수량만 최종 노출

6. 카드 진행 상태 관리
- 현재 인덱스/점수/선택 상태 관리
- `sessionStorage` 기반 새로고침 복구

7. YES/NO 인터랙션 확장
- 버튼 선택 지원
- 모바일/PC 포인터 드래그 스와이프 지원
- 드래그 거리 기반 페이드, 방향 배지(YES/NO) 노출

8. 결과 계산/이동
- 카드 응답의 `score_yes` / `score_no` 누적
- 활성 결과 중 최고점 `result_code` 선택
- 결과 URL로 이동

### Phase 3. 관리자 기능 확장

9. 테스트 편집 탭 운영
- `Basic / Cards / Results` 탭 구성
- 테스트 단위로 한 화면에서 운영 가능

10. 카드 관리 강화
- 카드 추가/수정/삭제
- `Card Image URL` + `Pick Media` 복구
- 등록 카드 리스트에서 `Edit/Delete` 동작

11. 결과 관리 강화
- 결과 추가/수정/삭제
- 중복 코드 방지/유효성 체크
- 결과 리스트 표시 및 상태 확인

12. JSON 일괄 등록
- `basic + cards + results` 전체 교체 방식 지원
- 검증용 JSON 샘플 파일 제공

### Phase 4. UI/안정화 개선

13. 카드 크기/가독성 조정
- 모바일/PC 카드 사이즈 소폭 축소
- 모바일 타이포/간격 보정

14. 스와이프 반응성 개선
- 스냅백/퇴장 충돌 완화
- 중복 입력/트윈 충돌 방지 보강

15. 결과 카드 UI 전환
- 완료 화면에서 결과 미리보기 카드 노출
- 카드 클릭으로 결과 링크 이동 (독립 결과 버튼 제거 방향 적용)

---

## 3) 관리자 사용 가이드

### Tests 메뉴

1. 새 테스트 생성
- `YesOrNo > Tests > New Test`

2. Basic 탭
- 제목/slug/설명
- 카드 뒷면 이미지
- 카드 노출 수량/풀 수량
- 그룹 쿼터 JSON
- JSON 일괄 등록

3. Cards 탭
- 카드 이미지 + 질문 + 보조텍스트 + 그룹 + 점수맵
- 저장 후 Registered Cards에서 수정/삭제

4. Results 탭
- `result_code`, `label`, 연결 글/페이지
- 결과 URL 자동 반영
- 결과 리스트에서 수정/삭제

---

## 4) 데이터 스키마 요약

### 카드(`cards[]`)

- `id`
- `image_url`
- `question_text`
- `sub_text`
- `group`
- `score_yes` (object)
- `score_no` (object)
- `active`

### 결과(`results[]`)

- `result_code`
- `label`
- `post_id`
- `result_url`
- `active`

---

## 5) 숏코드 사용법

```text
[yesorno yesorno="love-type"]
```

- `yesorno` 속성값으로 테스트 slug 조회
- slug 미지정 또는 실패 시 기본 활성 테스트 fallback

---

## 6) 검증 포인트 (운영 체크리스트)

1. 테스트 생성 후 리스트에 다건 표시되는지 확인
2. 카드 이미지 `Pick Media` 동작 확인
3. 카드 진행 시 YES/NO/스와이프 정상 확인
4. 새로고침 후 세션 복구 확인
5. 완료 시 결과 노출 및 링크 이동 확인
6. JSON 일괄등록 성공/실패 메시지 확인

---

## 7) 트러블슈팅

### 완료 화면이 구버전처럼 보일 때

- 원인 가능성:
  - 페이지 캐시/CDN 캐시/브라우저 캐시
  - 다른 플러그인(구 Taro Fortune) 또는 구 숏코드 충돌

- 권장 순서:
  1. WP 캐시 플러그인 Purge
  2. 서버 캐시(OPcache 포함) 정리
  3. CDN 캐시 Purge
  4. 브라우저 하드리로드

### 결과가 안 보일 때

- 결과관리에서 활성 결과(`active`) 여부 확인
- 결과 URL 설정 여부 확인
- 테스트의 결과 리스트가 저장되었는지 확인

---

## 8) 파일 구조 핵심

- `taro-fortune.php`: 플러그인 엔트리/상수
- `includes/class-taro-fortune.php`: 플러그인 런타임 등록
- `includes/class-taro-fortune-data-store.php`: CPT+메타 저장/조회
- `includes/class-taro-fortune-admin.php`: 관리자 페이지/저장 핸들러
- `includes/class-taro-fortune-frontend.php`: 숏코드 렌더/데이터 주입
- `assets/js/frontend.js`: 카드 진행/스와이프/결과 로직
- `assets/js/admin.js`: 미디어 선택/탭 UI
- `assets/css/frontend.css`: 프론트 카드/결과 스타일
- `assets/css/admin.css`: 관리자 레이아웃/폼 스타일

