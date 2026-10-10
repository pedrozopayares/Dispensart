# M1: quita la única petición de POST /api/transfers/{transfer}/void (la guarda debe nombrar la operación).
walk(if type == "object" and has("item") then
  .item |= map(select(((.request.method // "") == "POST" and ((.request.url.raw // "") | test("/void$"))) | not))
else . end)
