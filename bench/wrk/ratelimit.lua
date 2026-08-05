-- wrk -s bench/wrk/ratelimit.lua http://127.0.0.1:8080/api/products/limited
-- ApiRateLimitMiddleware: 5 req / 60s per IP+path → expect 200 then many 429

local counter = {}

request = function()
  wrk.headers["Accept"] = "application/json"
  return wrk.format(nil)
end

response = function(status, headers, body)
  counter[status] = (counter[status] or 0) + 1
end

done = function(summary, latency, requests)
  io.write("---- status codes (ratelimit: ~5x200 then 429) ----\n")
  local keys = {}
  for status, _ in pairs(counter) do
    keys[#keys + 1] = status
  end
  table.sort(keys)
  for _, status in ipairs(keys) do
    io.write(string.format("  HTTP %s: %d\n", status, counter[status]))
  end
  local ok = counter[200] or 0
  local limited = counter[429] or 0
  io.write(string.format("  ratio 429/total: %.1f%%\n", 100 * limited / math.max(1, summary.requests)))
  io.write(string.format("  first-window 200s (expect ~5): %d\n", ok))
  io.write("--------------------------------------------------\n")
end
