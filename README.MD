crazyDhtSpider
======
  This project is based on phpDhtSpider modification：https://github.com/cuijun123/phpDhtSpider
***
**[中文说明](README_CN.md)**

## Distributed DHT web crawler based on PHP

#########Software Operation Instructions##############

**dht_client directory** For the crawler server **Operating environment requirements**

1.Set server ulimit -n 65535

2.Server firewall needs to open port 6882 UDP protocol

3.run ./swoole-cli dht_client/client.php

**A lot of people don't collect data because of the second reason**

=============================================================

**dht_server directory** Receive data server (can be on the same server) **Operating environment requirements**

1.Set server ulimit -n 65535

2.The firewall opens the corresponding port of DHT_CLIENT request (in the configuration item, the default 2345 UDP protocol, if the server and the client are on the same machine, you can choose not to open

3.run ./swoole-cli dht_server/server.php and ./swoole-cli dht_client/client.php

=============================================================

1、There will be error logs during operation, which will not affect the use.If the log files are too large, you can clear them using scheduled tasks

2、Note that 'daemonize'=>false in config.php.  You can decide whether to start the background daemon or not

3、After the data volume reaches the level of one layer, it needs to be divided into tables or partitioned, otherwise the performance of MySQL will be very poor, please study by yourself。

4、It is recommended to find a more adequate flow of VPS to run, preferably unlimited flow

5、 At the beginning of the operation, because the node information is less, the data is relatively slow to obtain, the longer the operation time, the better the effect

6、This tool is mainly used for study and research. I shall not be responsible for any disputes or legal problems arising from the use of this tool


