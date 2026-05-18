import re
p = r"c:\\Users\\Asus\\Documents\\Project\\StockYamato\\resources\\views\\operator\\delivery\\index.blade.php"
with open(p, encoding='utf-8') as f:
    s = f.read()
# Extract content inside <script> tags
scripts = re.findall(r"<script[^>]*>([\s\S]*?)</script>", s, flags=re.I)
if not scripts:
    print('NO_SCRIPT')
    raise SystemExit(1)
js = scripts[-1]
print('--- length:', len(js))
counts = {c: js.count(c) for c in ['{','}','(',' )'.strip(),'[',']','`',"'",'"']}
print('counts:', counts)
# naive stack for braces
stack = []
pairs = {'{':'}','(':')','[':']'}
openers = set(pairs.keys())
closers = set(pairs.values())
line_no = 1
errors = []
for i,ch in enumerate(js):
    if ch == '\n':
        line_no += 1
    if ch in openers:
        stack.append((ch,line_no,i))
    elif ch in closers:
        if not stack:
            errors.append(('unmatched_closer', ch, line_no, i))
        else:
            last, lno, idx = stack[-1]
            if pairs[last] == ch:
                stack.pop()
            else:
                errors.append(('mismatch', last, ch, line_no, i))
print('stack_remaining:', len(stack))
if stack:
    for t in stack[-10:]:
        print('open at line', t[1], 'char', t[0])
print('errors sample:', errors[:10])
# check backtick pairing
bt = js.count('`')
print('backticks:', bt)
# find last 50 chars
print('last100:', repr(js[-200:]))
