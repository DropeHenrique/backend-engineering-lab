--[[
  KEYS[1] bucket hash key
  ARGV[1] capacity (tokens)
  ARGV[2] refill_tokens_per_second (float as string)
  ARGV[3] now (unix seconds, float as string)
  ARGV[4] consume (usually 1)
  Returns: { allowed (0|1), limit, remaining_float, reset_unix }
]]
local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local rate = tonumber(ARGV[2])
local now = tonumber(ARGV[3])
local consume = tonumber(ARGV[4])

local tokens_json = redis.call('HMGET', key, 't', 'l')
local tokens = tokens_json[1]
local last = tokens_json[2]

if not tokens then
  tokens = capacity
  last = now
else
  tokens = tonumber(tokens)
  last = tonumber(last)
  local delta = now - last
  if delta < 0 then delta = 0 end
  tokens = math.min(capacity, tokens + (delta * rate))
end

if tokens < consume then
  local need = consume - tokens
  local wait = need / rate
  local reset = math.floor(now + wait + 0.999)
  return {0, capacity, math.floor(tokens + 0.0001), reset}
end

tokens = tokens - consume
redis.call('HSET', key, 't', tostring(tokens), 'l', tostring(now))
local ttl = math.ceil(capacity / rate) + 120
if ttl < 60 then ttl = 60 end
redis.call('EXPIRE', key, ttl)
local reset = math.floor(now + ((capacity - tokens) / rate) + 0.999)
return {1, capacity, math.floor(tokens + 0.0001), reset}
