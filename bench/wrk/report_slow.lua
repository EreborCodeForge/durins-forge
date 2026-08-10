-- GET slow report (no auth)
-- DELAY_MS=50 QUERIES=5 wrk -t2 -c16 -d10s -s bench/wrk/report_slow.lua "http://127.0.0.1:8080/api/reports/slow?delay_ms=50&queries=5"

request = function()
  wrk.headers["Accept"] = "application/json"
  return wrk.format(nil)
end
