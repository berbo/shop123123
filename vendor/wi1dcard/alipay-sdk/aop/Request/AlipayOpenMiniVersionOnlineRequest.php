<?php
/**
 * ALIPAY API: alipay.open.mini.version.online request
 *
 * @author auto create
 *
 * @since  1.0, 2018-01-15 14:31:01
 */

namespace Alipay\Request;

class AlipayOpenMiniVersionOnlineRequest extends AbstractAlipayRequest
{
    /**
     * 小程序上架
     **/
    private $bizContent;

    public function setBizContent($bizContent)
    {
        $this->bizContent = $bizContent;
        $this->apiParams['biz_content'] = $bizContent;
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }
}
