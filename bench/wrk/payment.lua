-- POST payment simulate (Bearer + JSON body) + status breakdown
-- TOKEN=demo-token DELAY_MS=100 wrk -t2 -c16 -d10s -s bench/wrk/payment.lua http://127.0.0.1:8080/api/payments/simulate
--
-- Esperado com delay_ms=100 e 8 workers: ~80 req/s de HTTP 200 (não milhares).
-- Se Non-2xx alto e req/s alto: rota 404 (reinicie Eregion) ou 401/503.

local threads = {}
local token = os.getenv("TOKEN") or "demo-token"
local delay = os.getenv("DELAY_MS") or "100"
local body = string.format('{"amount":99.9,"method":"card","delay_ms":%s}', delay)

function setup(thread)
  table.insert(threads, thread)
end

function init(args)
  for code = 100, 599 do
    wrk.thread:set("s" .. tostring(code), 0)
  end
end

request = function()
  wrk.headers["Authorization"] = "Bearer " .. token
  wrk.headers["Content-Type"] = "application/json"
  wrk.headers["Accept"] = "application/json"
  return wrk.format("POST", wrk.path, wrk.headers, body)
end

local function bump(key)
  wrk.thread:set(key, (wrk.thread:get(key) or 0) + 1)
end

response = function(status, headers, body)
  bump("s" .. tostring(tonumber(status) or 0))
end

done = function(summary, latency, requests)
  local statuses = {}
  for _, thread in ipairs(threads) do
    for code = 100, 599 do
      local n = thread:get("s" .. tostring(code))
      if n and n > 0 then
        statuses[code] = (statuses[code] or 0) + n
      end
    end
  end
  io.write("---- HTTP status ----\n")
  local keys = {}
  for code, _ in pairs(statuses) do keys[#keys + 1] = code end
  table.sort(keys)
  for _, code in ipairs(keys) do
    io.write(string.format("  HTTP %d: %d\n", code, statuses[code]))
  end
  io.write("----------------------\n")
end
