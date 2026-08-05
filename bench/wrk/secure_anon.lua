-- wrk -s bench/wrk/secure_anon.lua http://127.0.0.1:8080/api/products/secure
-- Expect: HTTP 401 Unauthorized under load

local counter = {}

request = function()
  wrk.headers["Accept"] = "application/json"
  -- no Authorization
  return wrk.format(nil)
end

response = function(status, headers, body)
  counter[status] = (counter[status] or 0) + 1
end

done = function(summary, latency, requests)
  io.write("---- status codes (anon / expect 401) ----\n")
  local keys = {}
  for status, _ in pairs(counter) do
    keys[#keys + 1] = status
  end
  table.sort(keys)
  for _, status in ipairs(keys) do
    io.write(string.format("  HTTP %s: %d\n", status, counter[status]))
  end
  io.write("------------------------------------------\n")
end
