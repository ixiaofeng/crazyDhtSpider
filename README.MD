# crazyDhtSpider

![DHT Spider](https://neeko-copilot.bytedance.net/api/text2image?prompt=Distributed%20DHT%20web%20crawler%20architecture%20diagram%20with%20nodes%20and%20data%20flow%2C%20technical%20style%2C%20blue%20color%20scheme&image_size=square_hd)

This project is based on phpDhtSpider modification: [https://github.com/cuijun123/phpDhtSpider](https://github.com/cuijun123/phpDhtSpider)

## 📋 Project Overview

A distributed DHT web crawler implemented in PHP using Swoole, designed to efficiently collect and process DHT network data.

## 🚀 Quick Start

### Prerequisites

- Server with `ulimit -n 65535` set
- Firewall configured to allow required ports
- Swoole executable file placed in the project root directory

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ixiaofeng/crazyDhtSpider.git
   cd crazyDhtSpider
   ```

2. **Download Swoole executable**
   - Visit the [Swoole official website](https://www.swoole.com/)
   - Download the corresponding Swoole executable file
   - Place it in the same directory as `dht_client` and `dht_server`

3. **Configure the project**
   - Edit `dht_client/config.php` and `dht_server/config.php` as needed
   - Ensure database connections are properly configured

### Running the Crawler

#### dht_client (Crawler Server)

1. **Set server limits**
   ```bash
   ulimit -n 65535
   ```

2. **Open firewall port**
   ```bash
   # Example for Ubuntu/Debian
   ufw allow 6882/udp
   
   # Example for CentOS/RHEL
   firewall-cmd --permanent --add-port=6882/udp
   firewall-cmd --reload
   ```

3. **Start the client**
   ```bash
   ./swoole-cli dht_client/client.php
   ```

4. **Stop the client**
   ```bash
   # Find process
   ps aux|grep php_dht_client_master
   # Terminate process (use the found process ID)
   kill -2 <process_id>
   ```

#### dht_server (Data Receiving Server)

1. **Set server limits**
   ```bash
   ulimit -n 65535
   ```

2. **Open firewall port** (if server and client are on different machines)
   ```bash
   # Example for Ubuntu/Debian
   ufw allow 2345/udp
   
   # Example for CentOS/RHEL
   firewall-cmd --permanent --add-port=2345/udp
   firewall-cmd --reload
   ```

3. **Start the server and client**
   ```bash
   # Start server
   ./swoole-cli dht_server/server.php
   
   # Start client (in another terminal or background)
   ./swoole-cli dht_client/client.php
   ```

4. **Stop the server**
   ```bash
   # Find process
   ps aux|grep php_dht_server_master
   # Terminate process (use the found process ID)
   kill -2 <process_id>
   ```

## 📁 Project Structure

```
crazyDhtSpider/
├── dht_client/          # Crawler client directory
│   ├── client.php       # Main client script
│   ├── config.php       # Client configuration
│   └── inc/             # Client includes
├── dht_server/          # Data server directory
│   ├── server.php       # Main server script
│   ├── config.php       # Server configuration
│   └── inc/             # Server includes
├── import_infohash.php  # Infohash import Redis script
├── README.md            # English documentation
└── README_CN.md         # Chinese documentation
```

## ⚙️ Configuration

### Key Configuration Options

- `daemonize`: Set to `true` to run as a background daemon
- `server_port`: Port for the server to listen on (default: 2345)
- `client_port`: Port for the client to use (default: 6882)
- `database`: Database connection settings

### Redis Optimization (New Feature)

The project now uses Redis Set structure with binary storage for infohashes, reducing memory usage by approximately 63.6% for 10 million entries.

## 📊 Performance Tips

1. **Server Requirements**
   - VPS with sufficient bandwidth (unlimited traffic recommended)
   - At least 1GB RAM for handling moderate traffic
   - SSD storage for better database performance

2. **Database Optimization**
   - Implement table partitioning when data volume grows
   - Use appropriate indexes for frequently queried fields
   - Consider using read replicas for high-traffic scenarios

3. **Scaling**
   - Deploy multiple client instances across different servers
   - Use load balancing for the server component
   - Monitor system resources and adjust as needed

## 🚨 Common Issues

1. **No data being collected**
   - Ensure port 6882 UDP is open in the firewall
   - Check server limits with `ulimit -n`

2. **High error log volume**
   - Error logs are normal and don't affect functionality
   - Use scheduled tasks to clean up large log files

3. **Slow data collection initially**
   - This is normal as the crawler builds its node database
   - Performance improves over time as more nodes are discovered

## 📝 Notes

1. The crawler will generate error logs during operation, which is normal and doesn't affect functionality.
2. Consider enabling the background daemon mode (`daemonize => true`) for production deployments.
3. Monitor database performance and implement partitioning when necessary.
4. This tool is intended for educational and research purposes only. The author is not responsible for any disputes or legal issues arising from its use.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is open source under the MIT License.


