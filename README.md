# SimpleTracker

我们的 BitTorrent Tracker 服务器组件.

由于具备相当差的性能, 且在经过长时间优化和测试后，仍发现很难达到理想的目标, 故最终只能开源这一半成品.

## Redis (+MySQL)

为了不使用 MySQL, 需要通过设置 DBPort 为 null 来关闭相关依赖服务: 完成数统计及基于 SimpleTrackerKey 的服务.

## MySQL

上线初使用的版本.
