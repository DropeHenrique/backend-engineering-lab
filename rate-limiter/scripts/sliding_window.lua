--[[
  KEYS[1] zset key
  ARGV[1] now_ms (unix ms)
  ARGV[2] window_ms
  ARGV[3] limit (max count)
  ARGV[4] member (unique id)
  Returns: { allowed, limit, remaining, reset_unix }
]]
local key = KEYS[1]
local now_ms = tonumber(ARGV[1])
local window_ms = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local member = ARGV[4]
local min_ms = now_ms - window_ms

redis.call('ZREMRANGEBYSCORE', key, '-inf', min_ms)
local count = redis.call('ZCARD', key)

if count >= limit then
  local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
  local oldest_ms = tonumber(oldest[2])
  local reset_ms = oldest_ms + window_ms
  return {0, limit, 0, math.ceil(reset_ms / 1000)}
end

redis.call('ZADD', key, now_ms, member)
redis.call('EXPIRE', key, math.ceil(window_ms / 1000) + 10)
local remaining = limit - count - 1
local reset = math.ceil((now_ms + window_ms) / 1000)
return {1, limit, remaining, reset}
