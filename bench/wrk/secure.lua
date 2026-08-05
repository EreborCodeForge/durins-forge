-- wrk -s bench/wrk/secure.lua http://127.0.0.1:8080/api/products/secure
-- Expect: mostly 200 with Authorization; without token use secure_anon.lua

local counter = {}

request = function()
  wrk.headers["Authorization"] = "Bearer demo-token"
  wrk.headers["Accept"] = "application/json"
  return wrk.format(nil)
end

response = function(status, headers, body)
  local code = tonumber(status) or status
  counter[code] = (counter[code] or 0) + 1
end

done = function(summary, latency, requests)
  io.write("---- status codes ----\n")
  local keys = {}
  for status, _ in pairs(counter) do
    keys[#keys + 1] = status
  end
  table.sort(keys, function(a, b) return tonumber(a) < tonumber(b) end)
  for _, status in ipairs(keys) do
    io.write(string.format("  HTTP %s: %d\n", tostring(status), counter[status]))
  end
  io.write("----------------------\n")
end
