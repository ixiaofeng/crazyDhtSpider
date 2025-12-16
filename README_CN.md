# 🕷️ crazyDhtSpider

本项目是在 [phpDhtSpider](https://github.com/cuijun123/phpDhtSpider) 基础上修改而来。

🌐 **[English Documentation](README.MD)**

## 📋 项目概述

一个基于 PHP 和 Swoole 开发的高性能分布式 DHT 网络爬虫，专门用于高效爬取 BitTorrent DHT 网络数据。

## 🚀 快速开始

### 环境要求

- PHP 7.2+
- [Swoole](https://www.swoole.co.uk/) 扩展
- Linux/macOS 系统 (不推荐 Windows)

### 安装

 **克隆仓库**
   ```bash
   git clone https://github.com/ixiaofeng/crazyDhtSpider.git
   cd crazyDhtSpider
   ```



## 🛠️ 配置


### dht_client (爬虫服务器)

**目录**: `dht_client/`

**环境要求**:

1. **增加文件描述符限制**:
   ```bash
   ulimit -n 65535
   ```

2. **开放防火墙端口 (至关重要!)**:
   ```bash
   # 允许 UDP 端口 6882
   ```

3. **启动客户端**:
   ```bash
   ./swoole-cli dht_client/client.php
   ```
4. **关闭客户端**:
   ```bash
   ps aux|grep master
   # 找到主进程ID，假设为 1234
   # 终止主进程
   kill -2 1234
   ```

> ⚠️ **重要**: 很多用户采集不到数据是因为忘记开放 6882 端口！

### dht_server (数据接收服务器)

**目录**: `dht_server/`

**环境要求**:

1. **增加文件描述符限制**:
   ```bash
   ulimit -n 65535
   ```

2. **开放防火墙端口** (仅当服务器和客户端在不同机器上时需要):
   ```bash
   # 允许 UDP 端口 2345 (默认)
   ```

3. **启动服务端**:
   ```bash
   # 启动服务器
   ./swoole-cli dht_server/server.php
   
   ```
4. **关闭服务端**:
   ```bash
   # 查找主进程
   ps aux|grep server.php
   # 找到主进程ID，假设为 1234
   # 终止主进程
   kill -2 1234
   ```

## ⚙️ 高级设置

### config.php 配置选项

- `daemonize`: 设置为 `true` 以后台守护进程模式运行
- `worker_num`: 工作进程数量
- `task_worker_num`: 任务进程数量

### 数据库配置

编辑 `dht_server/database.php` 配置 MySQL 数据库连接信息。

### 其他

客户端运行后，会在dht_client目录下生成`node_id.dat`文件，该文件包含了客户端的节点ID，保证每次重启节点后，节点ID不变。
还会生成`router_table.dat`文件，该文件包含了客户端的路由表，用于存储其他节点的信息，默认一分钟更新一次。

## 📊 性能优化建议

1. **错误日志**: 运行过程中会生成错误日志，不影响正常使用。如果日志量过大，可以设置定时任务清理。

2. **数据库优化**: 当数据量增长到一定程度时，建议使用分表或分区来维护 MySQL 性能。

3. **服务器要求**: 使用流量充足的 VPS，最好是无限流量。

4. **初始数据采集**: 刚开始运行时，由于节点信息较少，数据采集可能较慢，随着时间推移，性能会逐渐提升。

## 📝 注意事项

- 本工具仅用于学习和研究 Swoole 相关知识
- 如在使用中产生任何纠纷或法律问题，本人概不负责

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来帮助改进项目！

## 📄 许可证

[MIT License](LICENSE)
