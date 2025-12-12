\# Contributing Guide (LangIT)



このリポジトリの開発フロー（ブランチ運用・PR運用）を統一するためのルールです。



---



\## 0. 大原則



\- `main` は \*\*常に動く状態\*\*（デプロイ可能な状態）を維持する

\- `main` へ \*\*直接 push しない\*\*（必ずPR経由）

\- 1PRは原則 \*\*1機能＝設計書の機能ID（Fxx\_yy）1つ\*\* を単位にする  

&nbsp; （例：F03\_01、F05\_02 など）



---



\## 1. ブランチ命名規則



形式：`<type>/fXX-YY-<short-desc>`（小文字・ハイフン）



type:

\- `feature/` 新規機能（設計書のFに対応）

\- `fix/` バグ修正

\- `refactor/` 挙動を変えない整理

\- `docs/` ドキュメント

\- `chore/` 設定・依存関係・雑務

\- `hotfix/` 緊急修正（mainから切る）



例：

\- `feature/f03-01-video-event`

\- `feature/f03-02-video-complete`

\- `feature/f05-01-test-show`

\- `feature/f05-02-test-score`

\- `docs/git-workflow`



---



\## 2. コミットメッセージ規則（最小）



形式：`type(scope): message`（scopeは任意）



type例：

\- `feat(video): ...`

\- `feat(test): ...`

\- `fix(auth): ...`

\- `docs: ...`

\- `refactor(progress): ...`

\- `chore: ...`



例：

\- `feat(video): add video complete endpoint`

\- `feat(test): implement scoring API`

\- `fix(auth): block inactive user`

\- `docs: add contributing guide`



---



\## 3. PRルール



\### PRタイトル

\- `feat(f05-02): test scoring`

\- `fix(f03-01): handle invalid position`



\### PR本文（最低限）

\- \*\*Spec\*\*：`F05\_02 / APIxx`（設計書の機能ID + API番号が分かるなら）

\- \*\*What\*\*：何をやったか（箇条書き3つ以内）

\- \*\*How to test\*\*：curl例 or 手順（1〜3行）

\- \*\*DB影響\*\*：migration/seed/手動SQLの有無（有なら内容）



---



\## 4. マージ方式



\- 原則 \*\*Squash merge\*\*

\- マージ後はブランチ削除



---



\## 5. 作業フロー（基本）



1\. 最新の `main` を取り込む

2\. 機能単位でブランチを切る

3\. コミットして push

4\. PRを作成 → レビュー → Squash merge で `main` に反映

5\. ローカルブランチ削除



---



\## 6. “Done” の定義（PRを出して良い条件）



\- ルート/Controller/Request/Service の変更が揃っている

\- ロール制御（student/admin）が崩れていない

\- 手動で最低1回は動作確認（curl等）できている

\- 仕様や手順が変わった場合はREADME/ドキュメントも更新する



---



