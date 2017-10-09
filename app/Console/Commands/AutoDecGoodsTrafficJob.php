<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\Goods;
use App\Http\Models\OrderGoods;
use App\Http\Models\User;
use Log;

class AutoDecGoodsTrafficJob extends Command
{
    protected $signature = 'command:autoDecGoodsTrafficJob';
    protected $description = '自动扣除到期流量包的流量';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $orderGoods = OrderGoods::where('is_expire', 0)->get();
        foreach ($orderGoods as $og) {
            $goods = Goods::where('id', $og->goods_id)->first();
            if (empty($goods)) {
                continue;
            }

            // 如果商品已过期，则需要扣流量
            if (date("Y-m-d H:i:s", strtotime("-" . $goods->days . " days")) >= $og->created_at) {
                $u = User::where('id', $og->user_id)->first();
                if (empty($u)) {
                    continue;
                }

                // 商品到期自动扣总流量
                if ($u->transfer_enable - $goods->traffic * 1048576 <= 0) {
                    User::where('id', $og->user_id)->update(['transfer_enable' => 0]);
                } else {
                    User::where('id', $og->user_id)->decrement('transfer_enable', $goods->traffic * 1048576);
                }

                OrderGoods::where('id', $og->id)->update(['is_expire' => 1]);
            }
        }

        Log::info('定时任务：' . $this->description);
    }
}
