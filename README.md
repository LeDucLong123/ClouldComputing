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
