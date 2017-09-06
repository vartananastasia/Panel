<?
namespace Panel;


/**
 * Class CheckDomain
 * @package Panel
 */
class CheckDomain{


    /**
     * inform after in minutes
     */
    const PERIOD = 30;

    /**
     * ask interval in minutes
     */
    const INTERVAL = 5;

    /**
     * phone to send sms to inform
     */
    const SMS_INFORM_NUMBER = '79104**1903';

    /**
     * Worker
     *
     * @return array
     */
    public function Action()
    {
        $domains = self::Domains();

        $status = [];
        foreach ($domains as $domain) {
            # status
            $url = $domain["UF_URL"];
            $res = self::checkDomain($url);

            # ping
            $host = $domain["UF_DOMAIN"];
            $port = 80;
            $waitTimeoutInSeconds = 1;
            $ping = 0;
            if($fp = fsockopen($host,$port,$errCode,$errStr,$waitTimeoutInSeconds))
                $ping = 1;
            fclose($fp);

            $status[] = [
                'domain_id' => $domain["ID"],
                'ping' => $ping,
                'domain' => $res,
            ];

            self::Log($domain["ID"], $ping, $res);
        }

        self::sms_inform($domains);

        return $status;
    }


    /**
     * Request to get status
     *
     * @param $url
     * @return int
     */
    private function checkDomain($url)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);

        return $response->getStatusCode();
    }


    /**
     * Write status and ping in table
     *
     * @param $domain
     * @param $ping
     * @param $status
     */
    private function Log($domain, $ping, $status){
        $checked = \Lexand\Hiload::GetHLEntityClass(\Lexand\Helper::PANEL_CHECK_DOMAIN);
        $checked::add(
            [
                'UF_DOMAIN' => $domain,
                'UF_STATUS' => $status,
                'UF_PING' => $ping,
                'UF_DATE' => date("d.m.Y H:i:s")
            ]
        );
    }


    /**
     * All domains to check
     *
     * @return array
     */
    private function Domains(){
        $domains = \Lexand\Hiload::GetHLItemsByID(\Lexand\Helper::PANEL_DOMAINS);

        $res = [];
        foreach ($domains as $d){
            $res[$d["ID"]] = $d;
        }
        return $res;
    }


    /**
     * send sms to inform if domains answer is not 200 status for last 30 min requests
     *
     * @param $domains
     */
    private function sms_inform($domains){
        $limit = count($domains) * self::PERIOD / self::INTERVAL;
        $status = \Lexand\Hiload::GetHLItemsByID(\Lexand\Helper::PANEL_CHECK_DOMAIN, $select = ['*'], $limit);

        $inform_arr = [];
        foreach ($status as $s){
            if (!$s["UF_STATUS"] == 200)
                $inform_arr[$s["UF_DOMAIN"]] += 1;
        }

        foreach ($inform_arr as $key => $i){
            if ($i >= 5){
                $message = "Сайт {$domains[$key]["UF_DOMAIN"]} не доступен";
                $sms_aero_user = \Lexand\Hiload::GetHLItemsByID(\Lexand\Helper::SMS_AERO_USER)[0];
                $send = new \SMS\Client($sms_aero_user['UF_LOGIN'], $sms_aero_user['UF_PASSWORD']);
                $sms = new \SMS\SMS($message, self::SMS_INFORM_NUMBER);
                $send->send_sms($sms, \SMS\Client::TYPE_7);
            }
            break;
        }
        return;
    }
}