-- Mazarbul API (Database::fetchAll): GET /api/benchmark/data
-- Hot stmt cache: GET /api/benchmark/data-hot
-- wrk -t2 -c16 -d10s -s bench/wrk/benchmark_data.lua http://127.0.0.1:8080/api/benchmark/data
-- Expect "driver":"mazarbul"
local threads = {}

function setup(thread)
  table.insert(threads, thread)
end

function init(args)
  for code = 100, 599 do
    wrk.thread:set("s" .. tostring(code), 0)
  end
end

request = function()
  wrk.headers["Accept"] = "application/json"
  return wrk.format(nil)
end

response = function(status, headers, body)
  local code = tonumber(status) or 0
  local key = "s" .. tostring(code)
  wrk.thread:set(key, (wrk.thread:get(key) or 0) + 1)
end

done = function(summary, latency, requests)
  io.write("---- HTTP status ----\n")
  local statuses = {}
  for _, thread in ipairs(threads) do
    for code = 100, 599 do
      local n = thread:get("s" .. tostring(code))
      if n and n > 0 then
        statuses[code] = (statuses[code] or 0) + n
      end
    end
  end
  local keys = {}
  for code, _ in pairs(statuses) do keys[#keys + 1] = code end
  table.sort(keys)
  for _, code in ipairs(keys) do
    io.write(string.format("  HTTP %d: %d\n", code, statuses[code]))
  end
  io.write("----------------------\n")
end
