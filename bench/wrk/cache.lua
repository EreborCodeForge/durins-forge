-- Contadores por thread (done() agrega via thread:get/set).
-- Público:  wrk -t4 -c100 -d15s --latency -s bench/wrk/cache.lua http://127.0.0.1:8080/api/products
-- Privado:  TOKEN=demo-token wrk -t4 -c100 -d15s --latency -s bench/wrk/cache.lua http://127.0.0.1:8080/api/products/secure

local threads = {}
local token = os.getenv("TOKEN") or "demo-token"

function setup(thread)
  table.insert(threads, thread)
end

function init(args)
  wrk.thread:set("hit", 0)
  wrk.thread:set("miss", 0)
  wrk.thread:set("bypass", 0)
  wrk.thread:set("other_hdr", 0)
  -- status codes as "s{code}" keys, e.g. s200, s502
end

request = function()
  wrk.headers["Authorization"] = "Bearer " .. token
  wrk.headers["Accept"] = "application/json"
  return wrk.format(nil)
end

local function bump(key)
  wrk.thread:set(key, (wrk.thread:get(key) or 0) + 1)
end

response = function(status, headers, body)
  local code = tonumber(status) or 0
  bump("s" .. tostring(code))

  local h = headers["X-Durin-Cache"] or headers["x-durin-cache"]
  if h == "HIT" then
    bump("hit")
  elseif h == "MISS" then
    bump("miss")
  elseif h == "BYPASS" then
    bump("bypass")
  else
    bump("other_hdr")
  end
end

done = function(summary, latency, requests)
  local hit, miss, bypass, other_hdr = 0, 0, 0, 0
  local statuses = {}

  for _, thread in ipairs(threads) do
    hit       = hit       + (thread:get("hit") or 0)
    miss      = miss      + (thread:get("miss") or 0)
    bypass    = bypass    + (thread:get("bypass") or 0)
    other_hdr = other_hdr + (thread:get("other_hdr") or 0)

    -- collect known status keys (wrk thread:get only for keys we set)
    for code = 100, 599 do
      local n = thread:get("s" .. tostring(code))
      if n and n > 0 then
        statuses[code] = (statuses[code] or 0) + n
      end
    end
  end

  io.write("---- X-Durin-Cache ----\n")
  io.write(string.format("  HIT:    %d\n", hit))
  io.write(string.format("  MISS:   %d\n", miss))
  io.write(string.format("  BYPASS: %d\n", bypass))
  io.write(string.format("  OTHER:  %d  (sem header X-Durin-Cache)\n", other_hdr))
  io.write("---- HTTP status ----\n")

  local keys = {}
  for code, _ in pairs(statuses) do
    keys[#keys + 1] = code
  end
  table.sort(keys)
  for _, code in ipairs(keys) do
    io.write(string.format("  HTTP %d: %d\n", code, statuses[code]))
  end
  if #keys == 0 then
    io.write("  (nenhum status capturado)\n")
  end
  io.write("----------------------\n")
end
