# 🕷️ crazyDhtSpider

This project is a modified version of [phpDhtSpider](https://github.com/cuijun123/phpDhtSpider).

🌐 **[中文说明](README_CN.md)**

## 📋 Overview

A high-performance distributed DHT web crawler based on PHP and Swoole, designed to efficiently crawl the BitTorrent DHT network.

## 🚀 Quick Start

### Requirements

- PHP 7.2+
- [Swoole](https://www.swoole.co.uk/) extension
- Linux/macOS system (Windows is not recommended)

### Installation

**Clone the repository**
   ```bash
   git clone https://github.com/ixiaofeng/crazyDhtSpider.git
   cd crazyDhtSpider
   ```

## 🛠️ Configuration

### dht_client (Crawler Server)

**Directory**: `dht_client/`

**Environment Requirements**:

1. **Increase file descriptor limit**:
   ```bash
   ulimit -n 65535
   ```

2. **Open firewall port (CRITICAL!)**:
   ```bash
   # Allow UDP port 6882
   ```

3. **Start the client**:
   ```bash
   ./swoole-cli dht_client/client.php
   ```
4. **Stop the client**:
   ```bash
   ps aux|grep master
   # Find the master process ID, assuming it's 1234
   # Terminate the master process
   kill -2 1234
   ```

> ⚠️ **Important**: Many users fail to collect data because they forget to open port 6882!

### dht_server (Data Receiver)

**Directory**: `dht_server/`

**Environment Requirements**:

1. **Increase file descriptor limit**:
   ```bash
   ulimit -n 65535
   ```

2. **Open firewall port** (only if server and client are on different machines):
   ```bash
   # Allow UDP port 2345 (default)
   ```

3. **Start the server**:
   ```bash
   # Start the server
   ./swoole-cli dht_server/server.php
   
   ```
4. **Stop the server**:
   ```bash
   # Find the master process
   ps aux|grep server.php
   # Find the master process ID, assuming it's 1234
   # Terminate the master process
   kill -2 1234
   ```

## ⚙️ Advanced Settings

### config.php Options

- `daemonize`: Set to `true` to run in background daemon mode
- `worker_num`: Number of worker processes
- `task_worker_num`: Number of task processes

### Database Configuration

Edit `dht_server/database.php` to configure your MySQL database connection.

### Other

After running the client, a `node_id.dat` file will be generated in the `dht_client` directory. This file contains the client's node ID, ensuring the node ID remains unchanged when the node is restarted.

A `router_table.dat` file will also be generated, which contains the client's routing table for storing information about other nodes. It is updated every minute by default.

## 📊 Performance Tips

1. **Error Logs**: The crawler will generate error logs during operation, which do not affect normal use. Consider setting up a cron job to clear large log files.

2. **Database Optimization**: When data volume grows, consider table partitioning or sharding to maintain MySQL performance.

3. **Server Requirements**: Use a VPS with sufficient bandwidth (unlimited bandwidth is recommended).

4. **Initial Data Collection**: Data collection may be slow initially as the crawler builds its node information database. Performance will improve over time.

## 📝 Notes

- This tool is primarily intended for **learning and research purposes** related to Swoole and DHT networks.
- The author is not responsible for any disputes or legal issues arising from the use of this tool.

## 🤝 Contributing

Contributions are welcome! Feel free to submit issues and pull requests.

## 📄 License

[MIT License](LICENSE)



