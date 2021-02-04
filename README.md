# 本项目是在phpDhtSpider基础上修改而来：https://github.com/cuijun123/phpDhtSpider
# 原作者不知什么原因一直不维护并且代码不完善，根本跑不起来，现在已经修复相关问题，并且进行了优化，开启了协程，可以高效率运行。

php实现的dht爬虫（分布式）

需要swoole拓展

swoole version 4.0 +

PHP 7.2+

swoole安装就不多介绍了，为了方便的话可以使用宝塔面板。

#########运行说明##############

**dht_client目录** 为爬虫服务器 **环境要求**

1.php安装swoole拓展

2.设置服务器 ulimit -n 100000

3.防火墙开放6882端口(切记！！！)

4.运行 php go.php

**很多采集不到数据 是由于第三点导致的**

=============================================================

**dht_server目录** 接受数据服务器(可在同一服务器) **环境要求**

1.php安装swoole拓展

2.设置服务器 ulimit -n 65535

3.防火墙开放dht_client请求的对应端口(配置项中，默认2345)

4.运行 php go.php

=============================================================

1、运行过程中会有少许错误日志，不影响使用，具体原因可以自己分析，可以根据自己的机器优化，但不要乱改参数不然小心报错日志疯。不是所有的东西都是越大越好（邪魅一笑）。

2、注意config.php中的'daemonize'=>false,//可以决定是否开启后台守护进程。

3、数据量达到一层程度后需要分表或者分区，不然mysql性能会很差，自己研究吧。

4、建议找一个流量比较充足的VPS来跑，最好是无限流量的,杜甫更好。内存一定要大，小鸡鸡就算了。

5、刚开始运行的时候因为节点信息获取的少，获取数据比较慢，很快速度就会上来。

6、如果用爬取的数据建站，记得过滤一下成人内容和政治敏感内容，否则后果自负。

7、还有部分未解决的问题，等我解决了个人问题后再解决吧。

