# Cloud Computing

## Phase 3: Cài đặt và cấu hình CloudWatch Agent để thu thập log Apache

Trong pha này, bạn sẽ cài đặt và cấu hình CloudWatch Agent trên máy chủ web để gửi các log truy cập (`access_log`) và lỗi (`error_log`) từ máy chủ Apache lên Amazon CloudWatch.

### 1. Cài đặt CloudWatch Agent

Thực hiện lệnh sau trên EC2:

```bash
sudo yum install -y amazon-cloudwatch-agent
```

Công cụ này giúp gửi log từ EC2 lên dịch vụ CloudWatch Logs.

---

### 2. Tải và cấu hình file `config.json`

Tải file cấu hình mẫu từ AWS S3 và di chuyển vào thư mục CloudWatch Agent:

```bash
wget https://aws-tc-largeobjects.s3.us-west-2.amazonaws.com/CUR-TF-200-ACCAP4-1-91575/capstone-4-clickstream/s3/config.json
sudo mv config.json /opt/aws/amazon-cloudwatch-agent/bin/
```

Xem nội dung:

```bash
sudo cat /opt/aws/amazon-cloudwatch-agent/bin/config.json
```

> File `config.json` định nghĩa:
>
> - Nguồn log (`access_log`, `error_log`).
> - Log group trong CloudWatch.
> - Thời gian giữ log.
> - Metrics cần thu thập.

---

### 3. Chuyển đổi định dạng log Apache sang JSON

CloudWatch Agent hoạt động hiệu quả hơn với log dạng JSON, vì vậy bạn cần chỉnh Apache để log theo định dạng này.

#### a. Chuẩn bị chỉnh sửa:

Tạo symlink để hiển thị file dễ hơn trên Cloud9:

```bash
ln -s /etc/httpd/conf /home/ec2-user/environment/httpdconf
```

Cấp quyền chỉnh sửa file:

```bash
sudo chown -R ec2-user /etc/httpd/conf
```

#### b. Chỉnh sửa file `httpd.conf`

Mở file `httpd.conf` và thực hiện:

**Sửa log lỗi (error log):**

Tìm dòng:

```apache
ErrorLog "logs/error_log"
```

Comment lại và thêm sau đó:

```apache
# ErrorLog "logs/error_log"
ErrorLog "/var/log/www/error/error_log"
ErrorLogFormat "{\"time\":\"%{%usec_frac}t\", \"function\" : \"[%-m:%l]\", \"process\" : \"[pid%P]\" ,\"message\" : \"%M\"}"
```

**Sửa log truy cập (access log):**

Tìm khối `<IfModule log_config_module>`, comment dòng `LogFormat ... combined`. Sau dòng `LogFormat ... common`, thêm:

```apache
LogFormat "{ \"time\":\"%{%Y-%m-%d}tT%{%T}t.%{msec_frac}tZ\", \"process\":\"%D\", \"filename\":\"%f\", \"remoteIP\":\"%a\", \"host\":\"%V\", \"request\":\"%U\", \"query\":\"%q\",\"method\":\"%m\", \"status\":\"%>s\", \"userAgent\":\"%{User-agent}i\",\"referer\":\"%{Referer}i\"}" cloudwatch
```

Tìm dòng:

```apache
CustomLog "logs/access_log" combined
```

Giữ nguyên, sau đó thêm dòng mới:

```apache
CustomLog "/var/log/www/access/access_log" cloudwatch
```

Nếu có khối `<IfModule logio_module>`, comment toàn bộ 3 dòng chưa được comment.

Lưu lại file `httpd.conf`.

---

### 4. Tạo thư mục log và khởi động lại dịch vụ

Tạo thư mục log:

```bash
sudo mkdir -p /var/log/www/access
sudo mkdir -p /var/log/www/error
```

Khởi động lại Apache:

```bash
sudo systemctl restart httpd
```

Khởi động CloudWatch Agent:

```bash
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
  -a fetch-config \
  -m ec2 \
  -c file:/opt/aws/amazon-cloudwatch-agent/bin/config.json \
  -s
```

Kiểm tra trạng thái Agent:

```bash
service amazon-cloudwatch-agent status
```

> Nếu hiển thị `active (running)` là bạn đã cài đặt thành công.

## Phase 4: Kiểm tra CloudWatch Agent và log clickstream

### 1. Kiểm tra file access_log

Truy cập ứng dụng web café và thực hiện một số hành động như xem menu, đặt hàng để tạo log.

Dùng lại lệnh như Phase 2 Task 3 để xem nội dung được ghi vào file log truy cập:

```bash
tail -f /var/log/www/access/access_log
```

> Lưu ý: log giờ đây sẽ được ghi dưới định dạng JSON.

---

### 2. Kiểm tra file `amazon-cloudwatch-agent.log`

Xác định vị trí file log của agent:

```bash
sudo cat /opt/aws/amazon-cloudwatch-agent/logs/amazon-cloudwatch-agent.log
```

Kiểm tra có các dòng như sau không:

```text
[inputs.logfile] Reading from offset 10620 in /var/log/www/error/error_log
[inputs.logfile] Reading from offset 19850 in /var/log/www/access/access_log
[logagent] piping log from apache/error/i-<instance-id> to cloudwatchlogs
[logagent] piping log from apache/access/i-<instance-id> to cloudwatchlogs
```

> Những dòng này xác nhận rằng agent đang đọc log và gửi lên CloudWatch. Bỏ qua các lỗi như `permission denied` trên `/sys/kernel/debug/tracing`.

---

### 3. Đặt hàng và kiểm tra log trên CloudWatch

- Truy cập website: `http://<public-ip>/cafe`
- Vào trang Menu, đặt một đơn hàng (sử dụng thiết bị di động nếu muốn thay đổi User-Agent).

Kiểm tra log:

- Mở AWS CloudWatch Console.
- Vào **Log Groups** → `apache/access`
- Mở log stream tương ứng.
- Mở rộng dòng log đầu tiên, xác minh hành động vừa thực hiện có được ghi nhận (dạng JSON).

> Clickstream data hiện đã được thu thập và lưu trên CloudWatch Logs thông qua CloudWatch Agent chạy trên web server.

## Phase 5: Sử dụng log giả lập và xác minh CloudWatch nhận đủ dữ liệu

Trong pha này, bạn sẽ thay thế file `access_log` hiện tại bằng một file log giả lập có sẵn. File này chứa nhiều dòng log truy cập hơn nhiều so với việc bạn tự tay truy cập website. Nhờ đó bạn có thể kiểm tra khả năng xử lý log ở quy mô lớn của CloudWatch Agent.

### 1. Phân tích file log giả lập

Xem vài dòng đầu của file log mẫu:

```bash
cat samplelogs/access_log.log | head
```

Xem dòng đầu dưới dạng JSON dễ đọc:

```bash
cat samplelogs/access_log.log | head -1 | python -m json.tool
```

Đếm số dòng log trong file:

```bash
cat samplelogs/access_log.log | wc -l
```

> Bạn sẽ thấy file này có rất nhiều log mô phỏng người dùng thực.

---

### 2. Thay thế file log thực bằng file log giả lập

Dừng CloudWatch Agent:

```bash
sudo systemctl stop amazon-cloudwatch-agent
```

Đặt file giả lập vào đúng vị trí CloudWatch Agent đọc log:

```bash
sudo cp samplelogs/access_log.log /var/log/www/access/access_log
```

> **Lưu ý:** Tên file phải là `access_log`, không phải `access_log.log`

Khởi động lại CloudWatch Agent:

```bash
sudo systemctl start amazon-cloudwatch-agent
```

---

### 3. Kiểm tra log giả lập trên CloudWatch

- Mở AWS CloudWatch Console
- Vào **Log Groups** → `apache/access`
- Mở log stream tương ứng
- Xác nhận:
  - Có **nhiều dòng log** xuất hiện
  - Có thể có **nhiều dòng cùng một timestamp** (do agent đọc nhanh file log lớn)

> Như vậy bạn đã xác minh thành công rằng log truy cập giả lập đã được gửi đầy đủ lên CloudWatch Logs.
