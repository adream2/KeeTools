# `.ssh/` — 项目内托管的 SSH 密钥

本目录存放本项目的 GitHub 部署密钥对，随项目目录一起保存，便于打包复用。

> ⚠️ **本目录已被 `.gitignore` 排除，密钥绝不入库。**
> 请勿删除 `.gitignore` 中的 `.ssh/*` 规则，也勿使用 `git add -f` 强制添加。

---

## 文件说明

| 文件 | 作用 | 可否外传 |
|---|---|---|
| `id_ed25519` | **私钥**，用于签名认证 | ❌ 绝不外传 |
| `id_ed25519.pub` | 公钥，已登记到 GitHub | ✅ 可公开 |
| `README.md` | 本文件 | ✅ |

- 密钥类型：ED25519
- 指纹：`SHA256:iqjaa2TbE2B9zUuqdcwxZV99d9TcyiY6gMb+bmzFhuE`
- 密码短语：无（便于自动化，也意味着**拿到文件即可推送**，务必妥善保管）

---

## 在项目中使用

本仓库已通过仓库级配置指向此处私钥，正常情况下无需任何操作：

```bash
git config core.sshCommand    # 查看当前配置
git push                      # 直接可用
```

### 换新电脑 / 重新 clone 后

密钥文件不会随 `git clone` 下载（被忽略），需手动把 `id_ed25519` 与 `id_ed25519.pub`
拷贝到新目录的 `.ssh/` 下，然后执行一次激活：

```powershell
# PowerShell（Windows）
powershell -ExecutionPolicy Bypass -File .ssh/setup.ps1
```

```bash
# 或手动配置（任意平台，路径按实际调整）
git config core.sshCommand "ssh -i <项目绝对路径>/.ssh/id_ed25519 -o IdentitiesOnly=yes"
```

`IdentitiesOnly=yes` 不可省略，否则 ssh 会优先尝试 `~/.ssh/` 下的其他密钥导致认证失败。

### 验证

```bash
ssh -i .ssh/id_ed25519 -o IdentitiesOnly=yes -T git@github.com
# 期望输出：Hi adream2/KeeTools! You've successfully authenticated...
```

---

## 公钥重新登记（仅当密钥更换时）

密钥对由**本地生成**，GitHub 只负责存放公钥：

```bash
ssh-keygen -t ed25519 -C adream2 -f .ssh/id_ed25519
cat .ssh/id_ed25519.pub     # 复制整行，粘贴到 GitHub
```

粘贴位置二选一：

| 位置 | 路径 | 特点 |
|---|---|---|
| **Deploy key** | 仓库 → Settings → Deploy keys | 仅本仓库；**须勾选 `Allow write access`** |
| 账号 SSH key | 头像 → Settings → SSH and GPG keys | 全账号通用，无只读限制 |

> 同名公钥只能登记在一处，重复添加会报 `Key is already in use`。

---

## 安全须知

1. **私钥文件请另行备份**（加密盘 / 私人网盘）。因为被 git 忽略，一旦项目目录被删且无备份，只能重新生成密钥并到 GitHub 重新登记。
2. 私钥**不得**出现在聊天记录、截图、邮件、公共仓库中。
3. 若怀疑私钥泄露，立即到 GitHub 删除对应公钥，然后重新生成密钥对。
4. 本机若启用 `ssh-agent`，建议用 `ssh-add .ssh/id_ed25519` 加载后由 agent 统一管理。
