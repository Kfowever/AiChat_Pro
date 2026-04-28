# AiChat Pro 审查摘要

基准环境：PHP 7.4、MySQL 5.7.26、Nginx 1.15.11。

## 已修复

- 运行栈不匹配：Docker 从 PHP 8.2/MySQL 8 调整为 PHP 7.4、MySQL 5.7.26、Nginx 1.15.11 分容器运行。
- 数据库环境变量优先级：`DB_HOST` 等 `.env`/容器变量现在优先于 `config/database.php`，避免 Docker 内误连 `localhost`。
- 敏感文件暴露：Nginx 与 `index.php` 均阻断 `.env`、`config/`、`sql/`、`storage/`、`docker/`、`.trae/`、`uploads/files/`。
- API Key 泄露：普通 `/api/models` 不再返回模型 `api_key`。
- 安装流程：改为统一 `/api/install/*`，安装后拒绝再次执行安装/升级动作。
- 请求滥用：新增文件型限流，覆盖登录、注册、聊天、上传、后台敏感写操作。
- 上传安全：校验扩展名、MIME、真实图片内容；上传文件不可被 Web 直接访问；头像独立公开目录。
- 前端 XSS：AI Markdown 输出先转义再渲染，并清理危险链接、事件属性和危险资源地址。
- 额度绕过：发送前按上下文和最大输出预估模型成本，余额不足时拒绝请求。
- 订阅绕过：普通订阅接口不再直接激活付费套餐，避免绕过支付。
- 后台可用性：补齐 `/admin` 管理页面，可登录、查看概览、维护模型和站点基础设置。

## 仍需人工确认

- 本机缺少可执行的 `php`、`docker`，且 `node.exe` 被系统拒绝访问，因此未能在本机执行 `php -l`、容器启动、真实 PHP-FPM/Nginx/MySQL 联调。
- 支付服务仍是占位实现；上线前必须接入支付宝/微信正式 SDK 并验证回调签名。
- PHP 7.4、MySQL 5.7、Nginx 1.15 均属于旧运行栈；若不是业务强约束，建议规划升级到仍受支持的版本。
