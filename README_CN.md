# crazyDhtSpider 

## 🌐 语言切换 
- [English README](README.md) | **中文 README** 

本项目是在phpDhtSpider基础上修改而来： `https://github.com/cuijun123/phpDhtSpider` 

## 📋 项目概述 

一个基于 PHP + Swoole 实现的分布式 DHT 网络爬虫，旨在高效收集和处理 DHT 网络数据。 

## 🚀 快速开始 

### 环境要求 

- 服务器已设置 `ulimit -n 65535` 
- 防火墙已开放所需端口 
- Swoole 可执行文件已放置于项目根目录 

### 安装步骤 

1. **克隆仓库** 
   ```bash 
   git clone https://github.com/ixiaofeng/crazyDhtSpider.git 
   cd crazyDhtSpider 
   ``` 

2. **下载 Swoole 可执行文件** 
   - 访问 `https://www.swoole.com/` 
   - 下载对应的 Swoole 可执行文件 
   - 放置于 `dht_client` 和 `dht_server` 同级目录 

3. **配置项目** 
   - 根据需要编辑 `dht_client/config.php` 和 `dht_server/config.php` 
   - 确保数据库连接设置正确 

### 运行爬虫 

#### dht_client（爬虫服务器） 

1. **设置服务器限制** 
   ```bash 
   ulimit -n 65535 
   ``` 

2. **开放防火墙端口** 
   ```bash 
   # Ubuntu/Debian 系统示例 
   ufw allow 6882/udp 
   
   # CentOS/RHEL 系统示例 
   firewall-cmd --permanent --add-port=6882/udp 
   firewall-cmd --reload 
   ``` 

3. **启动客户端** 
   ```bash 
   ./swoole-cli dht_client/client.php 
   ``` 

4. **停止客户端** 
   ```bash 
   # 查找进程 
   ps aux|grep php_dht_client_master 
   # 终止进程（使用查找到的进程号） 
   kill -2 <进程号> 
   ``` 

#### dht_server（数据接收服务器） 

1. **设置服务器限制** 
   ```bash 
   ulimit -n 65535 
   ``` 

2. **开放防火墙端口**（如果服务器和客户端在不同机器上） 
   ```bash 
   # Ubuntu/Debian 系统示例 
   ufw allow 2345/udp 
   
   # CentOS/RHEL 系统示例 
   firewall-cmd --permanent --add-port=2345/udp 
   firewall-cmd --reload 
   ``` 

3. **启动服务器和客户端** 
   ```bash 
   # 启动服务器 
   ./swoole-cli dht_server/server.php 
   
   # 启动客户端（在另一个终端或后台运行） 
   ./swoole-cli dht_client/client.php 
   ``` 

4. **停止服务器** 
   ```bash 
   # 查找进程 
   ps aux|grep php_dht_server_master 
   # 终止进程（使用查找到的进程号） 
   kill -2 <进程号> 
   ``` 

## 📁 项目结构 

``` 
crazyDhtSpider/ 
├── dht_client/          # 爬虫客户端目录 
│   ├── client.php       # 客户端主脚本 
│   ├── config.php       # 客户端配置 
│   └── inc/             # 客户端包含文件 
├── dht_server/          # 数据服务器目录 
│   ├── server.php       # 服务器主脚本 
│   ├── config.php       # 服务器配置 
│   └── inc/             # 服务器包含文件 
├── import_infohash.php  # Infohash导入Redis脚本 
├── README.md            # 英文文档 
└── README_CN.md         # 中文文档 
``` 

## ⚙️ 配置说明 

### dht_client/config.php 

#### 下载模式配置 
```php 
// 下载模式配置 
'enable_remote_download' => false,      // 是否启用远程下载转发 
'enable_local_download' => true,        // 是否启用本地下载 
'only_remote_requests' => false,        // 是否只处理来自其他服务器的下载请求 
``` 

**下载模式组合**：
1. **默认完整模式**（false, true, false）：本地执行下载，同时运行DHT爬虫，处理本地和远程下载请求
2. **远程转发模式**（true, false, false）：所有下载任务转发到远程服务器，本地只执行DHT爬虫
3. **双下载模式**（true, true, false）：优先使用远程下载转发，本地作为备用
4. **纯爬虫模式**（false, false, false）：只执行DHT爬虫，不处理任何下载请求
5. **专用下载服务器模式**（false, true, true）：只处理来自其他服务器的下载请求，不执行DHT爬虫
6. **限制模式**（任意, 任意, true）：当only_remote_requests为true时，会自动禁用下载转发，只处理远程下载请求

### dht_server/config.php 

主要配置包括 Swoole 服务器设置和数据库连接信息，可根据实际环境调整。

## 📊 性能优化建议 

1. **服务器要求** 
   - 具有足够带宽的 VPS（推荐无限流量） 
   - 至少 1GB 内存以处理中等流量 
   - SSD 存储以获得更好的数据库性能 

2. **数据库优化** 
   - 数据量增长时实现表分区 
   - 为频繁查询的字段使用适当的索引 
   - 高流量场景考虑使用读写分离 

3. **扩展建议** 
   - 在不同服务器上部署多个客户端实例 
   - 为服务器组件使用负载均衡 
   - 监控系统资源并根据需要调整 

## 🚨 常见问题 

1. **无法采集到数据** 
   - 确保防火墙已开放 6882 UDP 端口 
   - 检查服务器限制设置 `ulimit -n` 

2. **错误日志量大** 
   - 错误日志是正常的，不影响功能 
   - 使用定时任务清理大日志文件 

3. **初始数据采集缓慢** 
   - 这是正常现象，因为爬虫正在构建节点数据库 
   - 随着发现更多节点，性能会逐渐提高 

## 📝 注意事项 

1. 爬虫运行过程中会生成错误日志，这是正常的，不影响功能。 
2. 生产环境部署建议启用后台守护进程模式 (`daemonize => true`)。 
3. 监控数据库性能，必要时实现分区。 
4. 本工具仅用于学习和研究目的，作者不对使用过程中产生的任何纠纷或法律问题负责。 

## 🤝 贡献 

欢迎贡献！请随时提交 Pull Request。 

## 📄 许可证 

本项目基于 MIT 许可证开源。