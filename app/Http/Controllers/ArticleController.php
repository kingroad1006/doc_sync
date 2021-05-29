<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Fishtail\Http;

class ArticleController extends Controller
{
    /**
     * 文章发布状态 默认未发布 1是 2否
     * @var int[]
     */
    protected $publish_status = [
        'ydzx' => 2,
        'bjh' => 2,
        'souhu' => 2,
        'wangyi' => 2,
    ];

    /**
     * Note: 同步文章
     * User: wanglu
     * Date: 2021/5/25 上午10:39
     */
    public function sync()
    {
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush();
        header('X-Accel-Buffering: no');

        echo date('Y-m-d H:i:s') . ' 开始执行！' . "\n<br>";
        // 采集文章
        $articles = $this->collect();
        echo date('Y-m-d H:i:s') . ' 成功采集' . count($articles) . '篇文章' . "<br>";

        // 发布文章
        foreach ($articles as $k=>$article) {
            $num = $k + 1;
            echo date('Y-m-d H:i:s') . ' 正在同步第' . $num . '篇文章' . "<br>";

            // 获取文章发布状态
            $publish_status = $this->getArticlePublishStatus($article);

            sleep(1);
            // 一点资讯
            if ($publish_status['ydzx'] == 1) {
                echo date('Y-m-d H:i:s') . ' 一点资讯第' . $num . '篇文章已发布' . "<br>";
            } else {
                $this->ydzx($article);
            }
            sleep(1);
            // 百家号
            if ($publish_status['bjh'] == 1) {
                echo date('Y-m-d H:i:s') . ' 百家号第' . $num . '篇文章已发布' . "<br>";
            } else {
                $this->bjh($article);
            }
            sleep(1);
            // 网易号
            if ($publish_status['wangyi'] == 1) {
                echo date('Y-m-d H:i:s') . ' 网易号第' . $num . '篇文章已发布' . "<br>";
            } else {
                $this->wangyi($article);
            }
            sleep(1);
            // 搜狐号
            if ($publish_status['souhu'] == 1) {
                echo date('Y-m-d H:i:s') . ' 搜狐号第' . $num . '篇文章已发布' . "<br>";
            } else {
                $this->souhu($article);
            }

            // 更新文章发布状态
            $this->updateArticlePublishStatus($article);

        }
        echo date('Y-m-d H:i:s') . ' 执行结束，再见！' . "<br>";
    }

    /**
     * Note: 采集文章内容
     * User: wanglu
     * Date: 2021/5/25 上午10:45
     * @return array
     */
    private function collect()
    {
        // 获取列表文章地址
        $url = "http://chinasei.com.cn/zcjd/";
        $lines_string = file_get_contents($url);
        $rule = "/<!--文字列表页开始-->(.*?)\<!--文字列表页结束-->/s";
        preg_match($rule, $lines_string, $result);
        preg_match_all('/<li><span class=\"frBox\">(.*?)<\/span><a href=\"(.*?)\" target="_blank" title=\"(.*?)\">(.*?)<\/a><\/li>/is',$result[1],$lis);
        $articles = [];
        foreach ($lis[0] as $k=>$li) {
            if ($k >= 1) {
                break;
            }
            $articles[$k]['data'] = $lis[1][$k];
            $articles[$k]['url'] = $url . ltrim($lis[2][$k],  './');
            $articles[$k]['title'] = $lis[3][$k];
            // 获取文章内容(通过一点资讯的导入文章接口实现)
            $import_url = 'https://mp.yidianzixun.com/import_url?url=' . $articles[$k]['url'];
            $content = Http::get($import_url);
            $content = json_decode($content, true);
            if ($content['status'] == 'failed') {
                echo date('Y-m-d H:i:s') . ' 文章采集失败！' . "<br>";
            }
            $articles[$k]['content'] = $content['data']['content'];
            // 获取文章内容
            /*$lines_string = file_get_contents($articles[$k]['url']);
            $rule = "/<!--正文内容开始-->(.*?)\<!--正文内容结束-->/s";
            preg_match($rule, $lines_string, $res);
            $articles[$k]['content'] = str_replace("./", "http://chinasei.com.cn/zcjd/202105/", strip_tags($res[0],"<img><p></p><br />"));*/

        }
//        $articles = array_slice($articles, 0, 1);
        return $articles;
    }

    /**
     * Note: 一点资讯
     * User: wanglu
     * Date: 2021/5/25 上午11:30
     * @param $params
     */
    private function ydzx($params)
    {
        echo date('Y-m-d H:i:s') . ' 正在同步文章到一点咨询...' . "<br>";
        $header = [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json;charset=UTF-8'
        ];
        $rk = uniqid();

        echo date('Y-m-d H:i:s') . ' 登录中...' . "<br>";
        // 账户登录
        $login_url = "https://mp.yidianzixun.com/sign_in";
        $postData = [
            'username' => env('YDZX_ACCOUNT'),
            'password' => env('YDZX_PASSWORD')
        ];
        $user = Http::post($login_url, json_encode($postData), '', $header);
        $user = json_decode($user, true);
        if ($user['status'] == "failed") {
            echo date('Y-m-d H:i:s') . ' 登录失败！' . "<br>";
            return;
        }
        $cookie = $user['cookie'];
        echo date('Y-m-d H:i:s') . ' 登录成功！' . "<br>";

        echo date('Y-m-d H:i:s') . ' 发布中...' . "<br>";
        // 存储文章到草稿箱
        $article_url = 'https://mp.yidianzixun.com/model/Article?_rk=' . $rk;
        $postData = [
            'title' => $params['title'],
            'content' => $params['content']
        ];
        $article = Http::post($article_url, json_encode($postData), $cookie, $header);
        $article = json_decode($article, true);

        // 文章发布
        $publish_url = 'https://mp.yidianzixun.com/api/post/publish?post_id=' . $article['id'] . '&_rk=' . $rk;
        $publish = Http::get($publish_url, $cookie, $header);
        $publish = json_decode($publish, true);
        if ($publish['status'] == 'failed') {
            echo date('Y-m-d H:i:s') . ' 发布失败！' . $publish['reason'] . "<br>";
            return;
        }
        $this->publish_status['ydzx'] = 1;
        echo date('Y-m-d H:i:s') . ' 发布成功' . "<br>";
        echo date('Y-m-d H:i:s') . ' 一点咨询同步完成!' . "<br>";
    }

    /**
     * Note: 百家号
     * User: wanglu
     * Date: 2021/5/25 上午11:56
     * @param $params
     */
    private function bjh($params)
    {
        echo date('Y-m-d H:i:s') . ' 正在同步文章到百家号...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录中...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录成功！' . "<br>";
        echo date('Y-m-d H:i:s') . ' 发布中...' . "<br>";

        $header = [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json;charset=UTF-8'
        ];
        $article_id = uniqid();

        // 文章发布
        $publish_url = 'http://baijiahao.baidu.com/builderinner/open/resource/article/publish';
        $postData = [
            'app_id' => env('BJH_APP_ID'),
            'app_token' => env('BJH_APP_TOKEN'),
            'origin_url' => 'http://baijiahao.baidu.com/s?id=' . $article_id,
            'cover_images' => '',
            'is_original' => '1',
            'title' => $params['title'],
            'content' => $params['content']
        ];
        $publish = Http::post($publish_url, json_encode($postData), "", $header);
        $publish = json_decode($publish, true);
        if ($publish['errno'] != 0) {
            echo date('Y-m-d H:i:s') . ' 发布失败-' . $publish['errmsg'] . "<br>";
            return;
        }
        $this->publish_status['bjh'] = 1;
        echo date('Y-m-d H:i:s') . ' 发布成功' . "<br>";
        echo date('Y-m-d H:i:s') . ' 百家号同步完成!' . "<br>";
    }

    /**
     * Note: 网易号
     * User: wanglu
     * Date: 2021/5/25 上午11:56
     * @param $params
     */
    private function wangyi($params)
    {
        echo date('Y-m-d H:i:s') . ' 正在同步文章到网易号...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录中...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录成功！' . "<br>";
        echo date('Y-m-d H:i:s') . ' 发布中...' . "<br>";
        $header = [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
        ];
        $cookie = "_ntes_nuid=7f4dc2f74a3a98ff7bbd2b3b88f55faa; mail_psc_fingerprint=d950273aeeb36c857fee69002074db73; nts_mail_user=kingroad1006@163.com:-1:1; _ntes_nnid=7f4dc2f74a3a98ff7bbd2b3b88f55faa,1608877701073; _antanalysis_s_id=1621766603893; cm_newmsg=user%3Dfeng15803464706%40163.com%26new%3D2%26total%3D2; UserProvince=%u5317%u4EAC; NTES_CMT_USER_INFO=311237981%7C%E9%98%BF%E8%8E%AB%E8%AE%B2%E6%95%85%E4%BA%8B%7Chttp%3A%2F%2Fdingyue.ws.126.net%2F2021%2F0518%2Fa47edd17j00qtaavb000lc000be00bem.jpg%7Cfalse%7CZmVuZzE1ODAzNDY0NzA2QDE2My5jb20%3D; NTESwebSI=B5A0131E86903D38D756BE9D3F5AC289.hz-subscribe-user-docker-cm-online-sx6hk-xpxsx-xmit8-647d888p46-8081; NTES_SESS=DB.0rotsXo36bFX5rB9Md34mQmzDUgoFzHJg4qgFu88juWdmu57zsxJMq76o3.PgfIiLeOv1FnU1cpw9DrEFsAqhAnBrYr6i8WR58gnuV4je4VHV9YUPy7tC6oQgNx8Zo1S6jWLA8Pu_A4a87IiOcnmnPXtHON28X6f4ki9eKp1QzzFfvt.VA.xRku6a10iHq8s5VeDO5RKEJozvzmZgqwJvc; NTES_PASSPORT=aNWjujy.efYtK1N6kNb6.fUIKOVXvi1JB14MZHTOkPoJAZ_WA7Kw6gyufKmRqMEFcXoVpx5BspCsGgAXKRE38FQkKIW6x47CqIkPG33KSPUIxFgksRL4.U4zTeGYVznXu4hMEbUe3OZ0MaMeit3ICxoKvm4YtYtvnbKV9_.xnGpBTG_wZSA9kIXrvzQY7pIJpM5xJGgijb45g; S_INFO=1621825938|0|3&80##|feng15803464706; P_INFO=feng15803464706@163.com|1621825938|1|subscribe|00&99|bej&1621767771&subscribe#bej&null#10#0#0|&0|subscribe&newsclient|feng15803464706@163.com";

        // 文章发布
        $publish_url = 'https://mp.163.com/wemedia/article/status/api/publish.do?_=1621766829880&wemediaId=W554745401407064230';
        $postData = [
            'title' => $params['title'],
            'content' => $params['content'],
            'wemediaId' => 'W554745401407064230',
            'articleId' => '-1',
            'userClassify' => '财经/保险',
            'cover' => 'auto',
            'scheduled' => '0',
            'operation' => 'publish',
            'picUrl' => '',
            'NECaptchaValidate' => '', //验证码
            'sign' => '085A603C4E1',
            'timestamp' => time()
        ];
        $publish = Http::post($publish_url, http_build_query($postData), $cookie, $header);
        $publish = json_decode($publish, true);

        if ($publish['code'] != 0 || $publish['data'] == null) {
            echo date('Y-m-d H:i:s') . ' 发布失败-' . $publish['msg'] . "<br>";
            if ($publish['code'] == 100503) { // 操作频繁，需要验证码
                echo date('Y-m-d H:i:s') . ' 发布失败-操作频繁，请稍后再试' . "<br>";
            }
            return;
        }

        $this->publish_status['wangyi'] = 1;
        echo date('Y-m-d H:i:s') . ' 发布成功' . "<br>";
        echo date('Y-m-d H:i:s') . ' 网易号同步完成!' . "<br>";
    }

    /**
     * Note: 搜狐号
     * User: wanglu
     * Date: 2021/5/25 下午3:57
     * @param $params
     */
    private function souhu($params)
    {
        echo date('Y-m-d H:i:s') . ' 正在同步文章到搜狐号...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录中...' . "<br>";
        echo date('Y-m-d H:i:s') . ' 登录成功！' . "<br>";
        echo date('Y-m-d H:i:s') . ' 发布中...' . "<br>";
        $header = [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Referer: https://mp.sohu.com/mpfe/v3/main/news/addarticle?spm=smmp.articlelist.0.0&contentStatus=2&id=467293494'
        ];
        $cookie = "SUV=1811251206501327; gidinf=x099980107ee0ff14287d60760001f0aaa7fd8b8db9a; BAIDU_SSP_lcr=https://www.google.com.hk/; IPLOC=CN6101; reqtype=pc; spinfo=c29odXwxMDQ1NDc0Mzg0ODcwOTQwNjcyQHNvaHUuY29tfDEwNDU0NzQzODQ4NzA5NDA2NzI=; spsession=MTA0NTQ3NDM4NDg3MDk0MDY3MnwtMXwxNjIzOTk0MDA5fDEwNDU0NzQzODQ4NzA5NDA2NzIzMzI5NDAyOA==-213K9OKCJeTrBPnqa8JS9w3Nh8M=; jv=e09ed1a5cef66e7a186cef0298da750b-KUoP9Hhx1621926697117; ppinf=2|1621926697|1623136297|bG9naW5pZDowOnx1c2VyaWQ6Mjg6MTA0NTQ3NDM4NDg3MDk0MDY3MkBzb2h1LmNvbXxzZXJ2aWNldXNlOjMwOjAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMHxjcnQ6MTA6MjAyMS0wNS0yNXxlbXQ6MTowfGFwcGlkOjY6MTEzODA1fHRydXN0OjE6MXxwYXJ0bmVyaWQ6MTowfHJlbGF0aW9uOjA6fHV1aWQ6MTY6c2EwMmE1YjQyNmI2YTFjOHx1aWQ6MTY6c2EwMmE1YjQyNmI2YTFjOHx1bmlxbmFtZTowOnw; pprdig=Lk6m8MMhVdFOqWYacLwW-vYumNHhqo-tLQsqHQbpYMasabMBHD5zCgahISgTgt_GHjiN5_ttTergMwXkyhdDFuEnVjKn5MAf_OWqlEE40SaX6-WPD2uNGMgiTabitEu14thSdsAKJkEF6191y5zjKeK0VUZB46wTLcPAzhdI6RM; t=1621926799807; ppmdig=1621926697000000282da8a02af33ed38bcf6d985c3857ea";

        // 文章发布
        $publish_url = 'https://mp.sohu.com/mpbp/bp/news/v4/news/publish?accountId=120186912';
        $postData = [
            'title' => $params['title'],
            'content' => $params['content'],
        ];
        $publish = Http::post($publish_url, http_build_query($postData), $cookie, $header);
        $publish = json_decode($publish, true);
        if ($publish['success'] == false) {
            echo date('Y-m-d H:i:s') . ' 发布失败-' . $publish['msg']. "<br>";
            return;
        }
        $this->publish_status['souhu'] = 1;
        echo date('Y-m-d H:i:s') . ' 发布成功' . "<br>";
        echo date('Y-m-d H:i:s') . ' 搜狐号同步完成!' . "<br>";
    }

    /**
     * Note: 获取文章发布状态
     * User: wanglu
     * Date: 2021/5/28 下午5:35
     * @param $params
     * @return bool
     */
    protected function getArticlePublishStatus($params)
    {
        $where  = [
            ['title', '=', $params['title']],
        ];
        $row = Article::where($where)->first();
        if ($row == null) {
            // 存储文章到数据库
            $data = [
                'title' => $params['title'],
                'content' => $params['content'],
                'publish_status' => json_encode($this->publish_status),
            ];
            Article::insert($data);
        } else {
            $this->publish_status = json_decode($row->publish_status, true);
        }
        return $this->publish_status;
    }

    /**
     * Note: 更新文章发布状态
     * User: wanglu
     * Date: 2021/5/29 上午9:27
     * @param $params
     */
    protected function updateArticlePublishStatus($params)
    {
        $where = [
            'title' => $params['title']
        ];
        $data = [
            'publish_status' => json_encode($this->publish_status)
        ];
        Article::where($where)->update($data);
    }
}
