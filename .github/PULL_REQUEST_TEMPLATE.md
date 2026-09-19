<!-- 一个 PR 只做一件事。类型：新工具 / 工具改进 / 缺陷修复 / 文档 / 共享逻辑 -->

## 变更说明

<!-- 改了什么、为什么 -->

## 自检清单（提交前逐项确认）

- [ ] `index.html` 双击（`file://` 协议）可用，断网可用
- [ ] 零外部依赖：`python scripts/check_no_external.py` 通过
- [ ] manifest 与 ET-META 版本一致、CHANGELOG 已更新：`python scripts/check_manifest.py` 通过
- [ ] 共享逻辑已同步：`python scripts/sync_shared.py --check` 通过
- [ ] CSS 令牌检查通过（网站代码不写死颜色/尺寸）：`python scripts/check_css_tokens.py`
- [ ] 1024×768 与 1920×1080 下显示正常
- [ ] 无学生真实姓名等隐私数据作为示例数据
- [ ] 引用的第三方素材已在 `THIRD-PARTY-LICENSES.md` 登记

## 验证方式

<!-- 跑了哪些命令、在哪些环境试过 -->
