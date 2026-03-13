# crazyDhtSpider 

## 🌐 Language Switch 
- **English README** | [中文 README](README_CN.md) 

This project is modified from phpDhtSpider: `https://github.com/cuijun123/phpDhtSpider` 

## 📋 Project Overview 

A distributed DHT network crawler implemented based on PHP + Swoole, designed to efficiently collect and process DHT network data. 

## 🚀 Quick Start 

### Environment Requirements 

- Server has set `ulimit -n 65535` 
- Firewall has opened required ports 
- Swoole executable file has been placed in the project root directory 

### Installation Steps 

1. **Clone the repository** 
   ```bash 
   git clone https://github.com/ixiaofeng/crazyDhtSpider.git 
   cd crazyDhtSpider 
   ``` 

2. **Download Swoole executable file** 
   - Visit `https://www.swoole.com/` 
   - Download the corresponding Swoole executable file 
   - Place it in the directory at the same level as `dht_client` and `dht_server` 

3. **Configure the project** 
   - Edit `dht_client/config.php` and `dht_server/config.php` as needed 
   - Ensure database connection settings are correct 

### Run the Crawler 

#### dht_client (Crawler Server) 

1. **Set server limits** 
   ```bash 
   ulimit -n 65535 
   ``` 

2. **Open firewall ports** 
   ```bash 
   # Ubuntu/Debian system example 
   ufw allow 6882/udp 
   
   # CentOS/RHEL system example 
   firewall-cmd --permanent --add-port=6882/udp 
   firewall-cmd --reload 
   ``` 

3. **Start the client** 
   ```bash 
   ./swoole-cli dht_client/client.php 
   ``` 

4. **Stop the client** 
   ```bash 
   # Find the process 
   ps aux|grep php_dht_client_master 
   # Terminate the process (use the found process ID) 
   kill -2 <process_id> 
   ``` 

#### dht_server (Data Receiving Server) 

1. **Set server limits** 
   ```bash 
   ulimit -n 65535 
   ``` 

2. **Open firewall ports** (if server and client are on different machines) 
   ```bash 
   # Ubuntu/Debian system example 
   ufw allow 2345/udp 
   
   # CentOS/RHEL system example 
   firewall-cmd --permanent --add-port=2345/udp 
   firewall-cmd --reload 
   ``` 

3. **Start the server and client** 
   ```bash 
   # Start the server 
   ./swoole-cli dht_server/server.php 
   
   # Start the client (in another terminal or background) 
   ./swoole-cli dht_client/client.php 
   ``` 

4. **Stop the server** 
   ```bash 
   # Find the process 
   ps aux|grep php_dht_server_master 
   # Terminate the process (use the found process ID) 
   kill -2 <process_id> 
   ``` 

## 📁 Project Structure 

``` 
crazyDhtSpider/ 
├── dht_client/          # Crawler client directory 
│   ├── client.php       # Client main script 
│   ├── config.php       # Client configuration 
│   └── inc/             # Client include files 
├── dht_server/          # Data server directory 
│   ├── server.php       # Server main script 
│   ├── config.php       # Server configuration 
│   └── inc/             # Server include files 
├── import_infohash.php  # Infohash import Redis script 
├── README.md            # English documentation 
└── README_CN.md         # Chinese documentation 
``` 

## ⚙️ Configuration Guide 

### dht_client/config.php 

#### Download Mode Configuration 
```php 
// Download mode configuration 
'enable_remote_download' => false,      // Enable remote download forwarding 
'enable_local_download' => true,        // Enable local download 
'only_remote_requests' => false,        // Only handle download requests from other servers 
``` 

**Download mode combinations**:
1. **Default full mode** (false, true, false): Local download, run DHT crawler, handle local and remote download requests
2. **Remote forwarding mode** (true, false, false): All download tasks forwarded to remote server, local only runs DHT crawler
3. **Dual download mode** (true, true, false): Priority use remote download forwarding, local as backup
4. **Pure crawler mode** (false, false, false): Only run DHT crawler, do not handle any download requests
5. **Dedicated download server mode** (false, true, true): Only handle download requests from other servers, do not run DHT crawler
6. **Restricted mode** (any, any, true): When only_remote_requests is true, download forwarding is automatically disabled, only handle remote download requests

### dht_server/config.php 

Main configurations include Swoole server settings and database connection information, which can be adjusted according to the actual environment.

## 📊 Performance Optimization Suggestions 

1. **Server Requirements** 
   - VPS with sufficient bandwidth (unlimited traffic recommended) 
   - At least 1GB memory to handle medium traffic 
   - SSD storage for better database performance 

2. **Database Optimization** 
   - Implement table partitioning as data volume grows 
   - Use appropriate indexes for frequently queried fields 
   - Consider read-write separation for high-traffic scenarios 

3. **Scaling Suggestions** 
   - Deploy multiple client instances on different servers 
   - Use load balancing for server components 
   - Monitor system resources and adjust as needed 

## 🚨 Common Issues 

1. **Cannot collect data** 
   - Ensure firewall has opened 6882 UDP port 
   - Check server limit setting `ulimit -n` 

2. **Large error logs** 
   - Error logs are normal and do not affect functionality 
   - Use scheduled tasks to clean large log files 

3. **Initial data collection is slow** 
   - This is normal as the crawler is building its node database 
   - Performance will gradually improve as more nodes are discovered 

## 📝 Notes 

1. Error logs will be generated during operation, which is normal and does not affect functionality. 
2. For production deployment, it is recommended to enable background daemon mode (`daemonize => true`). 
3. Monitor database performance and implement partitioning if necessary. 
4. This tool is for learning and research purposes only. The author is not responsible for any disputes or legal issues arising from its use. 

## 🤝 Contribution 

Contributions are welcome! Please feel free to submit Pull Requests. 

## 📄 License 

This project is open source under the MIT License.