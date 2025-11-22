-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:7070
-- Generation Time: Nov 20, 2025 at 04:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stygianblue_dbv4`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_message`
--

CREATE TABLE `ai_chat_message` (
  `ID_MSG` bigint(20) NOT NULL,
  `ID_SESSION` bigint(20) NOT NULL,
  `ROLE` enum('user','assistant','system') NOT NULL,
  `NOI_DUNG` longtext DEFAULT NULL,
  `META_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`META_JSON`)),
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `ai_chat_message`
--

INSERT INTO `ai_chat_message` (`ID_MSG`, `ID_SESSION`, `ROLE`, `NOI_DUNG`, `META_JSON`, `CREATED_AT`) VALUES
(1, 5, 'user', 'chào', NULL, '2025-11-15 14:01:28'),
(2, 6, 'user', 'Chao', NULL, '2025-11-15 14:05:12'),
(3, 7, 'user', 'Hello', NULL, '2025-11-15 14:06:02'),
(4, 7, 'user', 'Hello', NULL, '2025-11-15 14:06:22'),
(5, 7, 'user', 'Hello', NULL, '2025-11-15 14:07:07'),
(6, 7, 'user', 'Hello', NULL, '2025-11-15 14:08:13'),
(7, 5, 'user', 'chào', NULL, '2025-11-15 14:10:19'),
(8, 5, 'user', 'a', NULL, '2025-11-15 14:11:04'),
(9, 5, 'user', 'a', NULL, '2025-11-15 14:24:28'),
(10, 5, 'user', 'x', NULL, '2025-11-15 14:26:38'),
(11, 5, 'user', 'a', NULL, '2025-11-15 14:29:17'),
(12, 5, 'user', 'a', NULL, '2025-11-15 14:34:28'),
(13, 5, 'assistant', 'Chào bạn! Stygian Blue rất vui được chào đón bạn.\n\nBạn cần tư vấn về dịch vụ chụp ảnh, đặt lịch, báo giá, hay có câu hỏi nào khác không? Hãy cho chúng tôi biết nhé!', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn! Stygian Blue rất vui được chào đón bạn.\\n\\nBạn cần tư vấn về dịch vụ chụp ảnh, đặt lịch, báo giá, hay có câu hỏi nào khác không? Hãy cho chúng tôi biết nhé!\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":96,\"candidatesTokenCount\":46,\"totalTokenCount\":247,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":96}],\"thoughtsTokenCount\":105},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"Bi0YaYvBIZCi2roPnI6SwQY\"}', '2025-11-15 14:34:30'),
(14, 5, 'user', 'mấy giờ stygin blue  hoạt động', NULL, '2025-11-15 14:34:57'),
(15, 5, 'assistant', 'Chào bạn! Stygian Blue hoạt động theo khung giờ sau:\n\n*   **Thứ Hai - Thứ Sáu:** 9:00 sáng - 6:00 chiều\n*   **Thứ Bảy:** 9:00 sáng - 5:00 chiều\n*   **Chủ Nhật:** Nghỉ (hoặc có thể mở theo lịch hẹn trước đặc biệt)\n\nTuy nhiên, chúng tôi luôn khuyến khích bạn đặt lịch hẹn trước để đảm bảo có thể phục vụ tốt nhất nhé!', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn! Stygian Blue hoạt động theo khung giờ sau:\\n\\n*   **Thứ Hai - Thứ Sáu:** 9:00 sáng - 6:00 chiều\\n*   **Thứ Bảy:** 9:00 sáng - 5:00 chiều\\n*   **Chủ Nhật:** Nghỉ (hoặc có thể mở theo lịch hẹn trước đặc biệt)\\n\\nTuy nhiên, chúng tôi luôn khuyến khích bạn đặt lịch hẹn trước để đảm bảo có thể phục vụ tốt nhất nhé!\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":154,\"candidatesTokenCount\":110,\"totalTokenCount\":453,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":154}],\"thoughtsTokenCount\":189},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"JC0YacaBGt2rjuMPtO7VwAo\"}', '2025-11-15 14:35:00'),
(16, 5, 'user', 'quy trình đặt lịch', NULL, '2025-11-15 14:56:12'),
(17, 5, 'assistant', 'Chào bạn! Quy trình đặt lịch tại Stygian Blue rất đơn giản và rõ ràng, giúp bạn dễ dàng có được buổi chụp ưng ý:\n\n1.  **Liên hệ & Chia sẻ:**\n    *   Bạn có thể liên hệ với Stygian Blue qua Zalo, Facebook Messenger hoặc số điện thoại của studio.\n    *   Hãy chia sẻ về nhu cầu của bạn: loại hình chụp (chân dung cá nhân, gia đình, sản phẩm, sự kiện...), mong muốn về concept, số lượng ảnh, hoặc bất kỳ ý tưởng nào bạn có.\n\n2.  **Tư vấn & Báo giá:**\n    *   Stygian Blue sẽ tư vấn các gói chụp phù hợp nhất dựa trên yêu cầu của bạn, gợi ý về concept, phong cách và địa điểm nếu cần.\n    *   Chúng tôi sẽ cung cấp báo giá chi tiết, rõ ràng cho gói dịch vụ bạn chọn.\n\n3.  **Xác nhận & Đặt cọc:**\n    *   Khi bạn đã đồng ý với gói chụp và báo giá, chúng ta sẽ cùng chọn ngày giờ cụ thể cho buổi chụp.\n    *   Để xác nhận lịch và đảm bảo sự cam kết, bạn sẽ thực hiện một khoản đặt cọc (thường là 30-50% tổng giá trị dịch vụ, sẽ được thông báo cụ thể).\n\n4.  **Chuẩn bị:**\n    *   Sau khi đặt cọc, Stygian Blue sẽ gửi bạn hướng dẫn chi tiết về cách chuẩn bị trước buổi chụp (ví dụ: trang phục, phụ kiện, makeup, tóc, những lưu ý nhỏ để bạn có tâm lý tốt nhất).\n    *   Chúng tôi sẽ chốt lại mọi chi tiết về concept, địa điểm, thời gian một lần nữa.\n\n5.  **Buổi Chụp:**\n    *   Đây là lúc chúng ta biến ý tưởng thành hiện thực! Đội ngũ của Stygian Blue sẽ hỗ trợ bạn nhiệt tình trong suốt buổi chụp để đảm bảo bạn có những khoảnh khắc đẹp nhất.\n\n6.  **Hậu kỳ & Giao ảnh:**\n    *   Sau buổi chụp, chúng tôi sẽ tiến hành chỉnh sửa và hoàn thiện ảnh theo đúng phong cách đã thỏa thuận.\n    *   Sản phẩm cuối cùng (ảnh kỹ thuật số, ảnh in, album...) sẽ được giao cho bạn đúng thời hạn đã hẹn.\n\n**Lưu ý nhỏ:** Để đảm bảo có lịch trống và chuẩn bị tốt nhất, bạn nên liên hệ đặt lịch sớm nhé!\n\nBạn có muốn đặt lịch ngay không, hay cần tư vấn thêm về dịch vụ nào?', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn! Quy trình đặt lịch tại Stygian Blue rất đơn giản và rõ ràng, giúp bạn dễ dàng có được buổi chụp ưng ý:\\n\\n1.  **Liên hệ & Chia sẻ:**\\n    *   Bạn có thể liên hệ với Stygian Blue qua Zalo, Facebook Messenger hoặc số điện thoại của studio.\\n    *   Hãy chia sẻ về nhu cầu của bạn: loại hình chụp (chân dung cá nhân, gia đình, sản phẩm, sự kiện...), mong muốn về concept, số lượng ảnh, hoặc bất kỳ ý tưởng nào bạn có.\\n\\n2.  **Tư vấn & Báo giá:**\\n    *   Stygian Blue sẽ tư vấn các gói chụp phù hợp nhất dựa trên yêu cầu của bạn, gợi ý về concept, phong cách và địa điểm nếu cần.\\n    *   Chúng tôi sẽ cung cấp báo giá chi tiết, rõ ràng cho gói dịch vụ bạn chọn.\\n\\n3.  **Xác nhận & Đặt cọc:**\\n    *   Khi bạn đã đồng ý với gói chụp và báo giá, chúng ta sẽ cùng chọn ngày giờ cụ thể cho buổi chụp.\\n    *   Để xác nhận lịch và đảm bảo sự cam kết, bạn sẽ thực hiện một khoản đặt cọc (thường là 30-50% tổng giá trị dịch vụ, sẽ được thông báo cụ thể).\\n\\n4.  **Chuẩn bị:**\\n    *   Sau khi đặt cọc, Stygian Blue sẽ gửi bạn hướng dẫn chi tiết về cách chuẩn bị trước buổi chụp (ví dụ: trang phục, phụ kiện, makeup, tóc, những lưu ý nhỏ để bạn có tâm lý tốt nhất).\\n    *   Chúng tôi sẽ chốt lại mọi chi tiết về concept, địa điểm, thời gian một lần nữa.\\n\\n5.  **Buổi Chụp:**\\n    *   Đây là lúc chúng ta biến ý tưởng thành hiện thực! Đội ngũ của Stygian Blue sẽ hỗ trợ bạn nhiệt tình trong suốt buổi chụp để đảm bảo bạn có những khoảnh khắc đẹp nhất.\\n\\n6.  **Hậu kỳ & Giao ảnh:**\\n    *   Sau buổi chụp, chúng tôi sẽ tiến hành chỉnh sửa và hoàn thiện ảnh theo đúng phong cách đã thỏa thuận.\\n    *   Sản phẩm cuối cùng (ảnh kỹ thuật số, ảnh in, album...) sẽ được giao cho bạn đúng thời hạn đã hẹn.\\n\\n**Lưu ý nhỏ:** Để đảm bảo có lịch trống và chuẩn bị tốt nhất, bạn nên liên hệ đặt lịch sớm nhé!\\n\\nBạn có muốn đặt lịch ngay không, hay cần tư vấn thêm về dịch vụ nào?\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":270,\"candidatesTokenCount\":545,\"totalTokenCount\":1792,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":270}],\"thoughtsTokenCount\":977},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"JTIYafW5Lt7Wg8UPu-CEyAo\"}', '2025-11-15 14:56:21'),
(18, 5, 'user', 'a', NULL, '2025-11-15 17:56:03'),
(19, 5, 'assistant', 'Bạn có câu hỏi nào khác không? Hay bạn muốn chúng tôi tư vấn thêm về một dịch vụ cụ thể, ví dụ như chụp ảnh chân dung, ảnh gia đình, hay ảnh sự kiện?', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Bạn có câu hỏi nào khác không? Hay bạn muốn chúng tôi tư vấn thêm về một dịch vụ cụ thể, ví dụ như chụp ảnh chân dung, ảnh gia đình, hay ảnh sự kiện?\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":818,\"candidatesTokenCount\":40,\"totalTokenCount\":858,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":818}]},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"RVwYaZK1A_2Ng8UP4O-_iQk\"}', '2025-11-15 17:56:05'),
(20, 5, 'user', 'bạn có dịch vụ chụp ảnh cưới không', NULL, '2025-11-20 15:36:04'),
(21, 5, 'user', 'bạn có dịch vụ chụp ảnh cưới không', NULL, '2025-11-20 16:32:28'),
(22, 5, 'assistant', 'Chào bạn!\n\nCó, Stygian Blue có cung cấp dịch vụ chụp ảnh cưới nhé!\n\nChúng tôi có các gói chụp đa dạng, từ ảnh cưới Pre-wedding (ảnh cưới ngoại cảnh/studio trước ngày cưới) đến chụp ảnh phóng sự/truyền thống trong ngày cưới.\n\nBạn có muốn chúng tôi tư vấn chi tiết hơn về các gói chụp ảnh cưới, phong cách, hoặc báo giá không? Hãy cho Stygian Blue biết nhé!', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn!\\n\\nCó, Stygian Blue có cung cấp dịch vụ chụp ảnh cưới nhé!\\n\\nChúng tôi có các gói chụp đa dạng, từ ảnh cưới Pre-wedding (ảnh cưới ngoại cảnh/studio trước ngày cưới) đến chụp ảnh phóng sự/truyền thống trong ngày cưới.\\n\\nBạn có muốn chúng tôi tư vấn chi tiết hơn về các gói chụp ảnh cưới, phong cách, hoặc báo giá không? Hãy cho Stygian Blue biết nhé!\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":879,\"candidatesTokenCount\":96,\"totalTokenCount\":1080,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":879}],\"thoughtsTokenCount\":105},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"L-AeabbZHpyBqfkP7JHR4Ag\"}', '2025-11-20 16:32:31'),
(23, 5, 'user', 'dữ liệu mà bạn dùng để trả lời được lấy từ đâu', NULL, '2025-11-20 16:33:16'),
(24, 5, 'assistant', 'Chào bạn!\n\nLà một trợ lý AI, tôi không \"lấy\" dữ liệu từ một trang web cụ thể nào trong thời gian thực. Thay vào đó, tôi được đào tạo trên một lượng lớn dữ liệu văn bản và mã nguồn từ internet và nhiều nguồn khác.\n\nKhi bạn tương tác với tôi trong vai trò trợ lý của Stygian Blue, tôi sử dụng thông tin và hướng dẫn đã được lập trình sẵn cho vai trò này để cung cấp cho bạn những câu trả lời chính xác và phù hợp nhất về các dịch vụ, quy trình và chính sách của studio, đúng như thể tôi là một nhân viên của Stygian Blue.\n\nNói cách khác, tôi đã được \"dạy\" để trả lời các câu hỏi về Stygian Blue dựa trên thông tin mà studio muốn truyền tải đến khách hàng.', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn!\\n\\nLà một trợ lý AI, tôi không \\\"lấy\\\" dữ liệu từ một trang web cụ thể nào trong thời gian thực. Thay vào đó, tôi được đào tạo trên một lượng lớn dữ liệu văn bản và mã nguồn từ internet và nhiều nguồn khác.\\n\\nKhi bạn tương tác với tôi trong vai trò trợ lý của Stygian Blue, tôi sử dụng thông tin và hướng dẫn đã được lập trình sẵn cho vai trò này để cung cấp cho bạn những câu trả lời chính xác và phù hợp nhất về các dịch vụ, quy trình và chính sách của studio, đúng như thể tôi là một nhân viên của Stygian Blue.\\n\\nNói cách khác, tôi đã được \\\"dạy\\\" để trả lời các câu hỏi về Stygian Blue dựa trên thông tin mà studio muốn truyền tải đến khách hàng.\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":990,\"candidatesTokenCount\":170,\"totalTokenCount\":1422,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":990}],\"thoughtsTokenCount\":262},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"YOAeacvPMP-7qfkPkqvFWA\"}', '2025-11-20 16:33:21'),
(25, 5, 'user', 'vậy làm sao để \"dạy\" bạn', NULL, '2025-11-20 16:33:56'),
(26, 5, 'assistant', 'Chào bạn! Đây là một câu hỏi rất hay về cách hoạt động của AI.\n\nĐể tôi có thể hoạt động như một trợ lý AI của Stygian Blue và tư vấn cho bạn, tôi được \"dạy\" theo hai cấp độ chính:\n\n1.  **Cấp độ 1: Định hình vai trò Stygian Blue (Hướng dẫn cụ thể cho cuộc trò chuyện này):**\n    *   Đây là cách trực tiếp nhất để tôi \"học\" về Stygian Blue.\n    *   Ngay từ đầu mỗi cuộc trò chuyện, tôi được cung cấp một bộ **hướng dẫn hệ thống (system instructions)** rất chi tiết.\n    *   Các hướng dẫn này bao gồm:\n        *   **Vai trò của tôi:** Trợ lý AI của studio chụp ảnh Stygian Blue.\n        *   **Tông giọng:** Thân thiện, ngắn gọn.\n        *   **Ngôn ngữ ưu tiên:** Tiếng Việt.\n        *   **Phạm vi kiến thức:** Chỉ tư vấn về dịch vụ chụp ảnh, lịch hẹn, báo giá, thanh toán, và các câu hỏi thường gặp của studio.\n        *   **Cách xử lý khi gặp câu hỏi ngoài phạm vi:** Lịch sự từ chối hoặc đề xuất hướng xử lý khác.\n    *   Nhờ những hướng dẫn này, tôi luôn biết cách phản hồi đúng theo yêu cầu và chính sách của Stygian Blue.\n\n2.  **Cấp độ 2: Nền tảng kiến thức chung (Đào tạo ban đầu của AI):**\n    *   Trước khi được \"định hình\" cho vai trò cụ thể này, tôi là một mô hình ngôn ngữ lớn (Large Language Model) được phát triển bởi Google.\n    *   Tôi đã được đào tạo trên một lượng khổng lồ dữ liệu văn bản và mã nguồn từ internet (sách, bài báo, trang web, diễn đàn, v.v.)\n    *   Quá trình đào tạo này giúp tôi hiểu ngôn ngữ tự nhiên, tạo ra văn bản mạch lạc, tổng hợp thông tin, trả lời các câu hỏi tổng quát và suy luận cơ bản.\n\nVì vậy, khi bạn hỏi tôi về Stygian Blue, tôi sử dụng nền tảng kiến thức ngôn ngữ chung của mình, kết hợp với các hướng dẫn cụ thể về vai trò của Stygian Blue để đưa ra câu trả lời chính xác nhất cho bạn.', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn! Đây là một câu hỏi rất hay về cách hoạt động của AI.\\n\\nĐể tôi có thể hoạt động như một trợ lý AI của Stygian Blue và tư vấn cho bạn, tôi được \\\"dạy\\\" theo hai cấp độ chính:\\n\\n1.  **Cấp độ 1: Định hình vai trò Stygian Blue (Hướng dẫn cụ thể cho cuộc trò chuyện này):**\\n    *   Đây là cách trực tiếp nhất để tôi \\\"học\\\" về Stygian Blue.\\n    *   Ngay từ đầu mỗi cuộc trò chuyện, tôi được cung cấp một bộ **hướng dẫn hệ thống (system instructions)** rất chi tiết.\\n    *   Các hướng dẫn này bao gồm:\\n        *   **Vai trò của tôi:** Trợ lý AI của studio chụp ảnh Stygian Blue.\\n        *   **Tông giọng:** Thân thiện, ngắn gọn.\\n        *   **Ngôn ngữ ưu tiên:** Tiếng Việt.\\n        *   **Phạm vi kiến thức:** Chỉ tư vấn về dịch vụ chụp ảnh, lịch hẹn, báo giá, thanh toán, và các câu hỏi thường gặp của studio.\\n        *   **Cách xử lý khi gặp câu hỏi ngoài phạm vi:** Lịch sự từ chối hoặc đề xuất hướng xử lý khác.\\n    *   Nhờ những hướng dẫn này, tôi luôn biết cách phản hồi đúng theo yêu cầu và chính sách của Stygian Blue.\\n\\n2.  **Cấp độ 2: Nền tảng kiến thức chung (Đào tạo ban đầu của AI):**\\n    *   Trước khi được \\\"định hình\\\" cho vai trò cụ thể này, tôi là một mô hình ngôn ngữ lớn (Large Language Model) được phát triển bởi Google.\\n    *   Tôi đã được đào tạo trên một lượng khổng lồ dữ liệu văn bản và mã nguồn từ internet (sách, bài báo, trang web, diễn đàn, v.v.)\\n    *   Quá trình đào tạo này giúp tôi hiểu ngôn ngữ tự nhiên, tạo ra văn bản mạch lạc, tổng hợp thông tin, trả lời các câu hỏi tổng quát và suy luận cơ bản.\\n\\nVì vậy, khi bạn hỏi tôi về Stygian Blue, tôi sử dụng nền tảng kiến thức ngôn ngữ chung của mình, kết hợp với các hướng dẫn cụ thể về vai trò của Stygian Blue để đưa ra câu trả lời chính xác nhất cho bạn.\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":1172,\"candidatesTokenCount\":506,\"totalTokenCount\":2785,\"cachedContentTokenCount\":969,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":1172}],\"cacheTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":969}],\"thoughtsTokenCount\":1107},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"jOAeaauvOputjuMPsdGF8As\"}', '2025-11-20 16:34:05'),
(27, 5, 'user', 'chào', NULL, '2025-11-20 16:35:41'),
(28, 5, 'assistant', 'Chào bạn! Đây là một câu hỏi rất thú vị!\n\nĐể tôi có thể tư vấn như một trợ lý của Stygian Blue, tôi nhận được một bộ hướng dẫn rõ ràng ngay từ khi bắt đầu cuộc trò chuyện này. Các hướng dẫn đó bao gồm:\n\n*   **Tôi là ai:** Trợ lý AI của studio chụp ảnh Stygian Blue.\n*   **Phong cách giao tiếp:** Thân thiện, ngắn gọn, ưu tiên tiếng Việt.\n*   **Nhiệm vụ chính:** Cung cấp hướng dẫn rõ ràng về dịch vụ chụp ảnh, lịch hẹn, báo giá, thanh toán, và các câu hỏi thường gặp của studio.\n*   **Giới hạn:** Lịch sự từ chối hoặc đề xuất hướng xử lý khác nếu câu hỏi vượt ngoài phạm vi.\n\nVề cơ bản, tôi là một mô hình ngôn ngữ lớn được đào tạo trên một lượng lớn dữ liệu để hiểu và tạo ra văn bản. Dựa trên nền tảng đó, bộ hướng dẫn cụ thể từ \"người tạo\" tôi giúp tôi định hình câu trả lời theo đúng yêu cầu của Stygian Blue.\n\nCó thể hình dung đơn giản là, tôi được \"chỉ dẫn\" về vai trò và nhiệm vụ của mình ngay từ đầu mỗi phiên trò chuyện, và tôi cố gắng thực hiện chúng một cách tốt nhất!', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Chào bạn! Đây là một câu hỏi rất thú vị!\\n\\nĐể tôi có thể tư vấn như một trợ lý của Stygian Blue, tôi nhận được một bộ hướng dẫn rõ ràng ngay từ khi bắt đầu cuộc trò chuyện này. Các hướng dẫn đó bao gồm:\\n\\n*   **Tôi là ai:** Trợ lý AI của studio chụp ảnh Stygian Blue.\\n*   **Phong cách giao tiếp:** Thân thiện, ngắn gọn, ưu tiên tiếng Việt.\\n*   **Nhiệm vụ chính:** Cung cấp hướng dẫn rõ ràng về dịch vụ chụp ảnh, lịch hẹn, báo giá, thanh toán, và các câu hỏi thường gặp của studio.\\n*   **Giới hạn:** Lịch sự từ chối hoặc đề xuất hướng xử lý khác nếu câu hỏi vượt ngoài phạm vi.\\n\\nVề cơ bản, tôi là một mô hình ngôn ngữ lớn được đào tạo trên một lượng lớn dữ liệu để hiểu và tạo ra văn bản. Dựa trên nền tảng đó, bộ hướng dẫn cụ thể từ \\\"người tạo\\\" tôi giúp tôi định hình câu trả lời theo đúng yêu cầu của Stygian Blue.\\n\\nCó thể hình dung đơn giản là, tôi được \\\"chỉ dẫn\\\" về vai trò và nhiệm vụ của mình ngay từ đầu mỗi phiên trò chuyện, và tôi cố gắng thực hiện chúng một cách tốt nhất!\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":1172,\"candidatesTokenCount\":279,\"totalTokenCount\":2398,\"cachedContentTokenCount\":969,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":1172}],\"cacheTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":969}],\"thoughtsTokenCount\":947},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"9OAeaYrZL5e94-EPgMbK4AQ\"}', '2025-11-20 16:35:49'),
(29, 5, 'user', 'chào', NULL, '2025-11-20 16:36:07'),
(30, 5, 'assistant', 'Đây là một câu hỏi rất thú vị!\n\nĐể \"dạy\" tôi trở thành trợ lý AI của studio Stygian Blue, quá trình diễn ra như sau:\n\n1.  **Huấn luyện nền tảng rộng lớn (bởi Google):**\n    *   Trước hết, tôi được phát triển bởi Google và được huấn luyện trên một lượng khổng lồ dữ liệu văn bản và mã nguồn từ internet. Quá trình này giúp tôi học cách hiểu ngôn ngữ tự nhiên, tạo ra văn bản mạch lạc, trả lời các câu hỏi về nhiều chủ đề khác nhau, và có kiến thức chung về thế giới. Đây là \"nền tảng\" để tôi có thể giao tiếp và xử lý thông tin.\n\n2.  **Cung cấp hướng dẫn và thông tin cụ thể cho vai trò Stygian Blue:**\n    *   Sau đó, để tôi có thể đóng vai trò là trợ lý của Stygian Blue, tôi được cung cấp một bộ **hướng dẫn cụ thể** (gọi là \"prompt\" hoặc \"chỉ dẫn ban đầu\"). Những hướng dẫn này bao gồm:\n        *   **Nhân vật/Vai trò:** \"Bạn là trợ lý AI của studio chụp ảnh Stygian Blue.\"\n        *   **Phong cách giao tiếp:** \"thân thiện, ngắn gọn, ưu tiên tiếng Việt.\"\n        *   **Phạm vi thông tin:** Rõ ràng về những loại câu hỏi tôi được phép trả lời (dịch vụ, lịch hẹn, báo giá, thanh toán, FAQ) và những gì tôi nên từ chối hoặc chuyển hướng.\n        *   **Dữ liệu cụ thể của studio:** Các thông tin về giờ hoạt động, quy trình đặt lịch, các loại hình dịch vụ (như chụp ảnh cưới, chân dung...), các chính sách chung của Stygian Blue.\n\n    *   **Ví dụ về cách \"dạy\":** Khi bạn hỏi về quy trình đặt lịch, tôi sẽ truy cập vào thông tin mà tôi đã được \"dạy\" về quy trình đó (liên hệ, tư vấn, báo giá, đặt cọc, chuẩn bị...) và diễn đạt lại theo đúng giọng điệu và phong cách của Stygian Blue.\n\nNói cách khác, tôi giống như một nhân viên mới được cung cấp một bộ tài liệu hướng dẫn công việc, chính sách của công ty và được yêu cầu giao tiếp với khách hàng theo một phong cách nhất định. Tôi sử dụng khả năng ngôn ngữ tự nhiên từ quá trình huấn luyện ban đầu kết hợp với các hướng dẫn cụ thể về Stygian Blue để đưa ra câu trả lời cho bạn.', '{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Đây là một câu hỏi rất thú vị!\\n\\nĐể \\\"dạy\\\" tôi trở thành trợ lý AI của studio Stygian Blue, quá trình diễn ra như sau:\\n\\n1.  **Huấn luyện nền tảng rộng lớn (bởi Google):**\\n    *   Trước hết, tôi được phát triển bởi Google và được huấn luyện trên một lượng khổng lồ dữ liệu văn bản và mã nguồn từ internet. Quá trình này giúp tôi học cách hiểu ngôn ngữ tự nhiên, tạo ra văn bản mạch lạc, trả lời các câu hỏi về nhiều chủ đề khác nhau, và có kiến thức chung về thế giới. Đây là \\\"nền tảng\\\" để tôi có thể giao tiếp và xử lý thông tin.\\n\\n2.  **Cung cấp hướng dẫn và thông tin cụ thể cho vai trò Stygian Blue:**\\n    *   Sau đó, để tôi có thể đóng vai trò là trợ lý của Stygian Blue, tôi được cung cấp một bộ **hướng dẫn cụ thể** (gọi là \\\"prompt\\\" hoặc \\\"chỉ dẫn ban đầu\\\"). Những hướng dẫn này bao gồm:\\n        *   **Nhân vật/Vai trò:** \\\"Bạn là trợ lý AI của studio chụp ảnh Stygian Blue.\\\"\\n        *   **Phong cách giao tiếp:** \\\"thân thiện, ngắn gọn, ưu tiên tiếng Việt.\\\"\\n        *   **Phạm vi thông tin:** Rõ ràng về những loại câu hỏi tôi được phép trả lời (dịch vụ, lịch hẹn, báo giá, thanh toán, FAQ) và những gì tôi nên từ chối hoặc chuyển hướng.\\n        *   **Dữ liệu cụ thể của studio:** Các thông tin về giờ hoạt động, quy trình đặt lịch, các loại hình dịch vụ (như chụp ảnh cưới, chân dung...), các chính sách chung của Stygian Blue.\\n\\n    *   **Ví dụ về cách \\\"dạy\\\":** Khi bạn hỏi về quy trình đặt lịch, tôi sẽ truy cập vào thông tin mà tôi đã được \\\"dạy\\\" về quy trình đó (liên hệ, tư vấn, báo giá, đặt cọc, chuẩn bị...) và diễn đạt lại theo đúng giọng điệu và phong cách của Stygian Blue.\\n\\nNói cách khác, tôi giống như một nhân viên mới được cung cấp một bộ tài liệu hướng dẫn công việc, chính sách của công ty và được yêu cầu giao tiếp với khách hàng theo một phong cách nhất định. Tôi sử dụng khả năng ngôn ngữ tự nhiên từ quá trình huấn luyện ban đầu kết hợp với các hướng dẫn cụ thể về Stygian Blue để đưa ra câu trả lời cho bạn.\"}],\"role\":\"model\"},\"finishReason\":\"STOP\",\"index\":0}],\"usageMetadata\":{\"promptTokenCount\":1172,\"candidatesTokenCount\":545,\"totalTokenCount\":2908,\"promptTokensDetails\":[{\"modality\":\"TEXT\",\"tokenCount\":1172}],\"thoughtsTokenCount\":1191},\"modelVersion\":\"gemini-2.5-flash\",\"responseId\":\"EeEeadrmCN3TjuMP76zJ-Qs\"}', '2025-11-20 16:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_session`
--

CREATE TABLE `ai_chat_session` (
  `ID_SESSION` bigint(20) NOT NULL,
  `ID_TK` varchar(20) DEFAULT NULL,
  `CLIENT_TOKEN` char(64) NOT NULL,
  `KENH` enum('web','zalo','messenger','khac') DEFAULT 'web',
  `BAT_DAU` datetime NOT NULL DEFAULT current_timestamp(),
  `KET_THUC` datetime DEFAULT NULL,
  `TRANG_THAI` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `ai_chat_session`
--

INSERT INTO `ai_chat_session` (`ID_SESSION`, `ID_TK`, `CLIENT_TOKEN`, `KENH`, `BAT_DAU`, `KET_THUC`, `TRANG_THAI`) VALUES
(1, NULL, '09c35c41fcb142b98d0c1bda368c51da7db68c171b51a1d46f5a98be28a0dc13', 'web', '2025-11-15 13:37:04', NULL, 'open'),
(2, NULL, '6e6521b52cf44c21b5d7dc1d9bf87b9382d1c0cd5643dfd5f3ee3d40c86525cd', 'web', '2025-11-15 13:37:14', NULL, 'open'),
(3, NULL, '9ed6e7a06704438c9f2afdd0b765c9a06e9c312c8a024ff544e4976d0d0795f0', 'web', '2025-11-15 13:38:46', NULL, 'open'),
(4, NULL, 'b2f9ce1c1a4d4574b6a421c0a47f8b3ca30f02fffbb4c8bf1ecbfa2b4363b090', 'web', '2025-11-15 13:38:56', NULL, 'open'),
(5, NULL, '6d6c6ac29cad4dc0844be12f5488d3a4944fa023039fb72041c7b27876721b9f', 'web', '2025-11-15 14:01:07', NULL, 'open'),
(6, NULL, '1f487fa2f486474ca04f15ef8b3d179d88b30f159d81e1e97ad0f1ddc0d5a726', 'web', '2025-11-15 14:05:05', NULL, 'open'),
(7, NULL, 'a8d9a2cc733a4ec8b7bf7b13eca1f5a6947e4bf344edf3b456e06b873d61dd13', 'web', '2025-11-15 14:05:45', NULL, 'open'),
(8, NULL, 'f2a26c5e3a384ad8970d2df5c2cf1bde1cf5f1ff6ef28dda47c59a5a690ca010', 'web', '2025-11-15 14:25:57', NULL, 'open');

-- --------------------------------------------------------

--
-- Table structure for table `ai_goi_y_dich_vu`
--

CREATE TABLE `ai_goi_y_dich_vu` (
  `ID_DEXUAT` bigint(20) NOT NULL,
  `ID_TK` varchar(20) DEFAULT NULL,
  `NGU_CANH` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`NGU_CANH`)),
  `DE_XUAT_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`DE_XUAT_JSON`)),
  `HIEU_LUC_TU` datetime DEFAULT NULL,
  `HIEU_LUC_DEN` datetime DEFAULT NULL,
  `DA_CHAP_NHAN` tinyint(1) DEFAULT 0,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bang_chung_thanh_toan`
--

CREATE TABLE `bang_chung_thanh_toan` (
  `ID_BCTT` int(11) NOT NULL,
  `ID_HD` int(11) NOT NULL,
  `TEP_MINH_CHUNG` varchar(255) NOT NULL,
  `GHI_CHU` varchar(255) DEFAULT NULL,
  `NGUOI_XAC_NHAN` varchar(20) DEFAULT NULL,
  `THOI_GIAN_XN` datetime DEFAULT NULL,
  `KET_QUA` enum('dong_y','tu_choi') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bao_gia_tam_tinh`
--

CREATE TABLE `bao_gia_tam_tinh` (
  `ID_BG` int(11) NOT NULL,
  `ID_LICHHEN` int(11) NOT NULL,
  `TONG_TAM_TINH` decimal(12,0) NOT NULL,
  `CHI_TIET_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`CHI_TIET_JSON`)),
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_customer_notes`
--

CREATE TABLE `branch_customer_notes` (
  `ID_NOTE` int(11) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `ID_TK` varchar(20) NOT NULL,
  `NOTE` text DEFAULT NULL,
  `TAG` enum('binh_thuong','tiem_nang','vip','nguy_co','can_cham_soc') DEFAULT 'binh_thuong',
  `LAST_CONTACT` date DEFAULT NULL,
  `NEXT_ACTION` date DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chi_nhanh`
--

CREATE TABLE `chi_nhanh` (
  `ID_CN` int(11) NOT NULL,
  `TEN_CN` varchar(100) DEFAULT NULL,
  `SDT_CN` varchar(12) DEFAULT NULL,
  `DIA_CHI_CN` varchar(100) DEFAULT NULL,
  `LATITUDE` decimal(10,7) DEFAULT NULL,
  `LONGITUDE` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `chi_nhanh`
--

INSERT INTO `chi_nhanh` (`ID_CN`, `TEN_CN`, `SDT_CN`, `DIA_CHI_CN`, `LATITUDE`, `LONGITUDE`) VALUES
(1, 'Hà Nội', '0123456789', '123 Đường Cầu Giấy, Hà Nội', 21.0277630, 105.8341600),
(2, 'Hồ Chí Minh', '0987654321', '456 Đường Lê Lợi, TP. HCM', 10.7768890, 106.7008060),
(3, 'Đà Nẵng', '0222333444', '789 Đường Nguyễn Văn Linh, Đà Nẵng', 16.0470790, 108.2062300),
(4, 'Hải Phòng', '0333444555', '101 Đường Trần Phú, Hải Phòng', 20.8449110, 106.6880840),
(5, 'Cần Thơ', '0444555666', '203 Đường Võ Văn Kiệt, Cần Thơ', 10.0451620, 105.7468530);

-- --------------------------------------------------------

--
-- Table structure for table `chi_phi_phat_sinh`
--

CREATE TABLE `chi_phi_phat_sinh` (
  `ID_CP` int(11) NOT NULL,
  `TEN_CP` varchar(100) NOT NULL,
  `MOTA_CP` varchar(255) DEFAULT NULL,
  `GIA_TRI` decimal(15,2) NOT NULL,
  `NGAY_GIO` datetime NOT NULL,
  `ID_CN` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chi_phi_phat_sinh`
--

INSERT INTO `chi_phi_phat_sinh` (`ID_CP`, `TEN_CP`, `MOTA_CP`, `GIA_TRI`, `NGAY_GIO`, `ID_CN`) VALUES
(5, 'Chi phí mặt bằng', 'Chi phí thuê mặt bằng tháng 2025-04', 400000.00, '2025-04-01 00:00:00', 2),
(6, 'Chi phí điện nước', 'Chi phí điện nước tháng 2025-04', 600000.00, '2025-04-01 00:00:00', 2),
(16, 'Chi phí mặt bằng', 'Chi phí thuê mặt bằng tháng 2025-04', 20000.00, '2025-04-01 00:00:00', 4),
(17, 'Chi phí điện nước', 'Chi phí điện nước tháng 2025-04', 300000.00, '2025-04-01 00:00:00', 4),
(18, 'Thuế VAT', 'Thuế 10% trên doanh thu tháng 2025-04', 120000.00, '2025-04-01 00:00:00', 4),
(28, 'Chi phí mặt bằng', 'Chi phí thuê mặt bằng tháng 2025-04', 3333333.00, '2025-04-01 00:00:00', 5),
(29, 'Chi phí điện nước', 'Chi phí điện nước tháng 2025-04', 4444444.00, '2025-04-01 00:00:00', 5),
(30, 'Thuế VAT', 'Thuế 10% trên doanh thu tháng 2025-04', 26500.00, '2025-04-01 00:00:00', 5),
(40, 'Chi phí mặt bằng', 'Chi phí thuê mặt bằng tháng 2025-04', 400000.00, '2025-04-01 00:00:00', 3),
(41, 'Chi phí điện nước', 'Chi phí điện nước tháng 2025-04', 3333333.00, '2025-04-01 00:00:00', 3),
(42, 'Thuế VAT', 'Thuế 10% trên doanh thu tháng 2025-04', 27500.00, '2025-04-01 00:00:00', 3),
(57, 'Chi phí mặt bằng', 'Chi phí thuê mặt bằng tháng 2025-04', 2.00, '2025-04-01 00:00:00', 1),
(58, 'Chi phí điện nước', 'Chi phí điện nước tháng 2025-04', 2.00, '2025-04-01 00:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `chi_tiet_hoa_don`
--

CREATE TABLE `chi_tiet_hoa_don` (
  `ID_CTHD` int(11) NOT NULL,
  `ID_HD` int(11) NOT NULL,
  `LOAI` varchar(20) NOT NULL,
  `ID_THAM_CHIEU` int(11) NOT NULL,
  `TEN_MUC` varchar(255) NOT NULL,
  `DON_GIA` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dich_vu`
--

CREATE TABLE `dich_vu` (
  `ID_DV` int(11) NOT NULL,
  `TEN_DV` varchar(100) DEFAULT NULL,
  `MOTA_DV` varchar(500) DEFAULT NULL,
  `IMAGE` varchar(200) DEFAULT NULL,
  `THOI_GIAN` int(11) NOT NULL,
  `TRANG_THAI` enum('draft','active','retired') NOT NULL DEFAULT 'active',
  `IS_DELETED` tinyint(1) NOT NULL DEFAULT 0,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `dich_vu`
--

INSERT INTO `dich_vu` (`ID_DV`, `TEN_DV`, `MOTA_DV`, `IMAGE`, `THOI_GIAN`, `TRANG_THAI`, `IS_DELETED`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 'Chụp hình cưới', 'Lưu giữ trọn vẹn từng khoảnh khắc hạnh phúc trong ngày trọng đại của cặp đôi. Bộ ảnh cưới sẽ được thực hiện với sự sáng tạo, lãng mạn, và đậm chất nghệ thuật, mang đậm dấu ấn riêng của cô dâu chú rể.', 'public/images/dichvu/anhcuoi.jpg', 480, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(2, 'Chụp hình chân dung', 'Ghi lại nét đẹp cá nhân qua các bức ảnh chân dung chuyên nghiệp, từ phong cách cổ điển đến hiện đại. Đây là cách tuyệt vời để lưu giữ hình ảnh cá nhân hoặc sử dụng cho mục đích truyền thông cá nhân.', 'public/images/dichvu/chandung.jpg', 120, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(4, 'Trang điểm', 'Cung cấp dịch vụ trang điểm chuyên nghiệp phù hợp với các dịp chụp ảnh như sự kiện, cưới hỏi, hoặc quảng cáo. Phong cách đa dạng từ tự nhiên đến lộng lẫy, đảm bảo làm nổi bật vẻ đẹp của khách hàng.', 'public/images/dichvu/pexels-kinkate-208052.jpg', 180, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(6, 'Chụp ảnh gia đình', 'Ghi lại những khoảnh khắc yêu thương, đầm ấm của cả gia đình qua các bức ảnh tự nhiên, đầy cảm xúc. Dịch vụ này phù hợp để lưu giữ kỷ niệm hoặc sử dụng trong các dịp đặc biệt.', 'public/images/dichvu/pexels-ketut-subiyanto-4546025.jpg', 180, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(7, 'Chụp hình quảng cáo', 'Tạo ra những bức ảnh chuyên nghiệp nhằm phục vụ cho mục đích quảng bá sản phẩm, dịch vụ hoặc thương hiệu. Đảm bảo hình ảnh bắt mắt, sắc nét, và thể hiện rõ thông điệp mà doanh nghiệp muốn truyền tải.', 'public/images/dichvu/pexels-laryssa-suaid-798122-1667088.jpg', 60, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(8, 'Chụp ảnh thẻ', 'Cung cấp ảnh thẻ nhanh chóng, đúng tiêu chuẩn, sắc nét và phù hợp với nhiều mục đích sử dụng như hồ sơ cá nhân, hồ sơ công việc, visa, hộ chiếu.', 'public/images/dichvu/339556239_670242441778333_2053857324053008066_n.jpg', 180, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(9, 'Chụp ảnh sự kiện', 'Dịch vụ chụp ảnh chuyên nghiệp cho các sự kiện như hội nghị, lễ khai trương, các buổi họp mặt, hoặc sự kiện giải trí. Đảm bảo bắt trọn những khoảnh khắc quan trọng và tạo nên hình ảnh ấn tượng để lưu giữ hoặc sử dụng cho truyền thông.', 'public/images/dichvu/sukien.jpg', 480, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09'),
(25, 'Chụp ảnh kỷ yếu ', 'abcd', 'public/images/dichvu/kyyeu.jpg', 480, 'active', 0, '2025-11-20 21:22:09', '2025-11-20 21:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `dich_vu_hinh_anh`
--

CREATE TABLE `dich_vu_hinh_anh` (
  `ID_HA` int(11) NOT NULL,
  `ID_DV` int(11) NOT NULL,
  `URL` varchar(255) NOT NULL,
  `ALT_TEXT` varchar(255) DEFAULT NULL,
  `THU_TU` int(11) NOT NULL DEFAULT 1,
  `IS_COVER` tinyint(1) NOT NULL DEFAULT 0,
  `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `dich_vu_hinh_anh`
--

INSERT INTO `dich_vu_hinh_anh` (`ID_HA`, `ID_DV`, `URL`, `ALT_TEXT`, `THU_TU`, `IS_COVER`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 1, 'public/images/dichvu/anhcuoi.jpg', 'Ảnh bìa dịch vụ #1', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(2, 2, 'public/images/dichvu/chandung.jpg', 'Ảnh bìa dịch vụ #2', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(4, 4, 'public/images/dichvu/pexels-kinkate-208052.jpg', 'Ảnh bìa dịch vụ #4', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(5, 6, 'public/images/dichvu/pexels-ketut-subiyanto-4546025.jpg', 'Ảnh bìa dịch vụ #6', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(6, 7, 'public/images/dichvu/pexels-laryssa-suaid-798122-1667088.jpg', 'Ảnh bìa dịch vụ #7', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(7, 8, 'public/images/dichvu/339556239_670242441778333_2053857324053008066_n.jpg', 'Ảnh bìa dịch vụ #8', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(8, 9, 'public/images/dichvu/sukien.jpg', 'Ảnh bìa dịch vụ #9', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16'),
(9, 25, 'public/images/dichvu/kyyeu.jpg', 'Ảnh bìa dịch vụ #25', 1, 1, 1, '2025-10-22 16:10:16', '2025-10-22 16:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `don_gia_dich_vu`
--

CREATE TABLE `don_gia_dich_vu` (
  `ID_DV` int(11) NOT NULL,
  `NGAY_GIO` datetime NOT NULL,
  `DON_GIA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `don_gia_dich_vu`
--

INSERT INTO `don_gia_dich_vu` (`ID_DV`, `NGAY_GIO`, `DON_GIA`) VALUES
(1, '2024-10-01 10:00:00', 150000),
(2, '2024-10-02 11:00:00', 100000),
(4, '2024-10-04 13:00:00', 300000),
(6, '2025-04-23 02:41:57', 350000),
(7, '2025-04-18 07:12:10', 250000),
(8, '2025-04-18 07:12:33', 50000),
(9, '2025-04-23 03:01:27', 500000),
(9, '2025-04-23 08:02:53', 200000),
(25, '2025-04-23 02:18:26', 120000);

-- --------------------------------------------------------

--
-- Table structure for table `don_gia_trang_phuc`
--

CREATE TABLE `don_gia_trang_phuc` (
  `ID_TP` int(11) NOT NULL,
  `NGAY_GIO` datetime NOT NULL,
  `DON_GIA` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `don_gia_trang_phuc`
--

INSERT INTO `don_gia_trang_phuc` (`ID_TP`, `NGAY_GIO`, `DON_GIA`) VALUES
(2, '2025-11-05 02:10:43', 300000),
(3, '2025-11-05 02:10:43', 300000),
(4, '2025-11-05 02:10:43', 300000),
(5, '2025-11-05 02:10:43', 300000),
(6, '2025-11-05 02:10:43', 300000),
(7, '2025-11-05 02:18:42', 30000),
(8, '2025-11-05 14:00:37', 200000);

-- --------------------------------------------------------

--
-- Table structure for table `don_thue_trang_phuc`
--

CREATE TABLE `don_thue_trang_phuc` (
  `ID_TTP` int(11) NOT NULL,
  `ID_TK` varchar(20) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `NGAY_DAT` datetime NOT NULL DEFAULT current_timestamp(),
  `NGAY_NHAN` datetime NOT NULL,
  `NGAY_TRA_DK` datetime NOT NULL,
  `NGAY_TRA_TT` datetime DEFAULT NULL,
  `TRANG_THAI` enum('cho_duyet','da_duyet','dang_thue','da_tra','tre_hen','huy') DEFAULT 'cho_duyet',
  `TIEN_COC` int(11) NOT NULL DEFAULT 0,
  `TONG_TIEN_DU_KIEN` int(11) NOT NULL DEFAULT 0,
  `TONG_TIEN_THUC_TE` int(11) DEFAULT NULL,
  `GHI_CHU` varchar(255) DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `don_thue_trang_phuc`
--

INSERT INTO `don_thue_trang_phuc` (`ID_TTP`, `ID_TK`, `ID_CN`, `NGAY_DAT`, `NGAY_NHAN`, `NGAY_TRA_DK`, `NGAY_TRA_TT`, `TRANG_THAI`, `TIEN_COC`, `TONG_TIEN_DU_KIEN`, `TONG_TIEN_THUC_TE`, `GHI_CHU`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 'trongnghia', 5, '2025-11-05 14:44:15', '2025-11-06 10:00:00', '2025-11-08 18:00:00', NULL, 'cho_duyet', 270000, 900000, NULL, '- Liên hệ: Trọng Nghĩa · 0909300040 · trongnghiann4911@gmail.com', '2025-11-05 14:44:15', '2025-11-05 14:44:15');

-- --------------------------------------------------------

--
-- Table structure for table `don_thue_trang_phuc_ct`
--

CREATE TABLE `don_thue_trang_phuc_ct` (
  `ID_TTP` int(11) NOT NULL,
  `ID_TP` int(11) NOT NULL,
  `SO_LUONG` int(11) NOT NULL DEFAULT 1,
  `DON_GIA_AP_DUNG` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `don_thue_trang_phuc_ct`
--

INSERT INTO `don_thue_trang_phuc_ct` (`ID_TTP`, `ID_TP`, `SO_LUONG`, `DON_GIA_AP_DUNG`) VALUES
(1, 2, 1, 300000);

--
-- Triggers `don_thue_trang_phuc_ct`
--
DELIMITER $$
CREATE TRIGGER `trg_check_overlap_before_insert` BEFORE INSERT ON `don_thue_trang_phuc_ct` FOR EACH ROW BEGIN
  DECLARE cnt INT;
  /* ID_TTP đang insert đã có NGAY_NHAN/NGAY_TRA_DK */
  SELECT COUNT(*) INTO cnt
  FROM don_thue_trang_phuc t
  JOIN don_thue_trang_phuc_ct c ON c.ID_TTP = t.ID_TTP
  WHERE c.ID_TP = NEW.ID_TP
    AND t.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')
    AND (
      t.NGAY_NHAN <= (SELECT NGAY_TRA_DK FROM don_thue_trang_phuc WHERE ID_TTP = NEW.ID_TTP)
      AND t.NGAY_TRA_DK >= (SELECT NGAY_NHAN FROM don_thue_trang_phuc WHERE ID_TTP = NEW.ID_TTP)
    );

  IF cnt > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trang phục đã được đặt/thuê trong khoảng thời gian này.';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_check_overlap_before_update` BEFORE UPDATE ON `don_thue_trang_phuc_ct` FOR EACH ROW BEGIN
  DECLARE cnt INT;

  SELECT COUNT(*) INTO cnt
  FROM don_thue_trang_phuc t
  JOIN don_thue_trang_phuc_ct c ON c.ID_TTP = t.ID_TTP
  WHERE c.ID_TP = NEW.ID_TP
    AND t.ID_TTP <> NEW.ID_TTP
    AND t.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')
    AND (
      t.NGAY_NHAN <= (SELECT NGAY_TRA_DK FROM don_thue_trang_phuc WHERE ID_TTP = NEW.ID_TTP)
      AND t.NGAY_TRA_DK >= (SELECT NGAY_NHAN FROM don_thue_trang_phuc WHERE ID_TTP = NEW.ID_TTP)
    );

  IF cnt > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trang phục đã được đặt/thuê trong khoảng thời gian này.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `goi_dich_vu`
--

CREATE TABLE `goi_dich_vu` (
  `ID_GOI` int(11) NOT NULL,
  `TEN_GOI` varchar(150) NOT NULL,
  `MO_TA` varchar(500) DEFAULT NULL,
  `HINH_ANH` varchar(200) DEFAULT NULL,
  `HIEU_LUC_TU` datetime DEFAULT NULL,
  `HIEU_LUC_DEN` datetime DEFAULT NULL,
  `TRANG_THAI` enum('nhap','ban','ngung') DEFAULT 'ban'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `goi_dich_vu`
--

INSERT INTO `goi_dich_vu` (`ID_GOI`, `TEN_GOI`, `MO_TA`, `HINH_ANH`, `HIEU_LUC_TU`, `HIEU_LUC_DEN`, `TRANG_THAI`) VALUES
(1, 'Gói Cưới Trọn Gói', 'Bao gồm chụp hình cưới, trang điểm cô dâu và thuê trang thiết bị cơ bản. Phù hợp cho các cặp đôi muốn chuẩn bị nhanh gọn và tiết kiệm chi phí.', 'public/images/combo/goi_cuoi.jpg', '2025-01-01 00:00:00', '2026-01-01 00:00:00', 'ban'),
(2, 'Gói Gia Đình Hạnh Phúc', 'Gói chụp ảnh gia đình bao gồm make-up nhẹ, chụp tại studio và tặng 5 tấm in khổ lớn.', 'public/images/combo/goi_giadinh.jpg', '2025-01-01 00:00:00', '2026-01-01 00:00:00', 'ban'),
(3, 'Gói Kỷ Yếu Trường Lớp', 'Dịch vụ chụp kỷ yếu trọn gói cho nhóm học sinh, sinh viên. Bao gồm chụp ngoại cảnh, make-up nhóm và quay flycam.', 'public/images/combo/goi_kyyeu.jpg', '2025-02-01 00:00:00', '2026-02-01 00:00:00', 'ban'),
(4, 'Gói Sự Kiện Doanh Nghiệp', 'Bao gồm chụp ảnh sự kiện, quay video và xử lý hậu kỳ cơ bản. Thích hợp cho các hội nghị, buổi khai trương, hoặc event lớn.', 'public/images/combo/goi_sukien.jpg', '2025-01-15 00:00:00', '2026-01-15 00:00:00', 'ban'),
(5, 'Gói Cá Nhân Cao Cấp', 'Phù hợp cho khách hàng cá nhân muốn chụp chân dung, có make-up và xử lý ảnh chuyên sâu.', 'public/images/combo/goi_canhan.jpg', '2025-03-01 00:00:00', '2026-03-01 00:00:00', 'ban'),
(10, 'Gói Sự Kiện Doanh Nghiệp Pro', 'Gói quay/chụp sự kiện doanh nghiệp với máy ảnh full-frame, đèn LED công suất cao, gimbal chống rung. Phù hợp cho lễ khai trương, hội nghị khách hàng, kickoff nội bộ.', 'public/images/combo/goi_sukien_pro.jpg', '2025-04-20 00:00:00', '2026-04-20 00:00:00', 'ban');

-- --------------------------------------------------------

--
-- Table structure for table `goi_dich_vu_chi_tiet`
--

CREATE TABLE `goi_dich_vu_chi_tiet` (
  `ID_GOI` int(11) NOT NULL,
  `ID_DV` int(11) NOT NULL,
  `SO_LUONG` int(11) DEFAULT 1,
  `DON_GIA_AP_DUNG` int(11) DEFAULT NULL,
  `THU_TU` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `goi_dich_vu_chi_tiet`
--

INSERT INTO `goi_dich_vu_chi_tiet` (`ID_GOI`, `ID_DV`, `SO_LUONG`, `DON_GIA_AP_DUNG`, `THU_TU`) VALUES
(1, 1, 1, 150000, 1),
(1, 4, 1, 300000, 2),
(2, 4, 1, 200000, 2),
(2, 6, 1, 350000, 1),
(3, 4, 1, 300000, 2),
(3, 25, 1, 120000, 1),
(4, 7, 1, 250000, 2),
(4, 9, 1, 500000, 1),
(5, 2, 1, 100000, 1),
(5, 4, 1, 200000, 2),
(10, 2, 1, 100000, 2),
(10, 4, 1, NULL, 3),
(10, 9, 1, 500000, 1);

-- --------------------------------------------------------

--
-- Table structure for table `goi_dich_vu_thiet_bi`
--

CREATE TABLE `goi_dich_vu_thiet_bi` (
  `ID_GOI` int(11) NOT NULL,
  `ID_TB` int(11) NOT NULL,
  `SO_LUONG` int(11) DEFAULT 1,
  `GHI_CHU` varchar(255) DEFAULT NULL,
  `ID_CN` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `goi_dich_vu_thiet_bi`
--

INSERT INTO `goi_dich_vu_thiet_bi` (`ID_GOI`, `ID_TB`, `SO_LUONG`, `GHI_CHU`, `ID_CN`) VALUES
(10, 9, 1, 'Gimbal chống rung để quay di chuyển mượt', NULL),
(10, 10, 1, 'Máy ảnh full-frame chính', NULL),
(10, 11, 2, 'Đèn LED công suất cao / ánh sáng mềm', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hoa_don`
--

CREATE TABLE `hoa_don` (
  `ID_HD` int(11) NOT NULL,
  `NGAY_GIO` datetime DEFAULT NULL,
  `ID_LICHHEN` int(11) NOT NULL,
  `TONG_TIEN` decimal(10,0) DEFAULT NULL,
  `TRANGTHAI_THANHTOAN` enum('Chưa thanh toán','Đã thanh toán') NOT NULL DEFAULT 'Chưa thanh toán',
  `PHUONGTHUC_THANHTOAN` varchar(50) DEFAULT 'Chuyển khoản ngân hàng',
  `YEU_CAU_XAC_NHAN` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `hoa_don`
--

INSERT INTO `hoa_don` (`ID_HD`, `NGAY_GIO`, `ID_LICHHEN`, `TONG_TIEN`, `TRANGTHAI_THANHTOAN`, `PHUONGTHUC_THANHTOAN`, `YEU_CAU_XAC_NHAN`) VALUES
(17, '2025-04-14 00:28:54', 41, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(23, '2025-04-14 01:15:37', 44, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(27, '2025-04-14 01:51:55', 41, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(28, '2025-04-14 01:53:21', 41, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(29, '2025-04-14 02:04:54', 41, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(30, '2025-04-14 02:05:49', 41, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(34, '2025-04-20 04:12:21', 50, 200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 0),
(35, '2025-04-20 05:30:18', 51, 1200000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(37, '2025-04-21 04:55:03', 53, 2400000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(38, '2025-04-21 05:02:02', 54, 2400000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(39, '2025-04-21 05:23:27', 55, 600000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(41, '2025-04-23 16:09:03', 57, 4000000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1),
(42, '2025-11-15 10:14:25', 60, 200000, 'Chưa thanh toán', 'Chuyển khoản ngân hàng', 0),
(43, '2025-11-15 10:17:24', 61, 1200000, 'Đã thanh toán', 'VNPAY', 0),
(44, '2025-11-18 22:32:59', 63, 150000, 'Chưa thanh toán', 'Chuyển khoản ngân hàng', 0);

-- --------------------------------------------------------

--
-- Table structure for table `hoa_don_thue_trang_phuc`
--

CREATE TABLE `hoa_don_thue_trang_phuc` (
  `ID_HD` int(11) NOT NULL,
  `ID_TTP` int(11) NOT NULL,
  `LOAI` enum('coc','thanh_toan','phu_phi') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `khach_hang`
--

CREATE TABLE `khach_hang` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_QUYEN` int(11) DEFAULT NULL,
  `HO_TEN` varchar(50) DEFAULT NULL,
  `NGAY_SINH` date DEFAULT NULL,
  `DIA_CHI` varchar(100) DEFAULT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `SDT` varchar(12) DEFAULT NULL,
  `MAT_KHAU` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `khach_hang`
--

INSERT INTO `khach_hang` (`ID_TK`, `ID_QUYEN`, `HO_TEN`, `NGAY_SINH`, `DIA_CHI`, `EMAIL`, `SDT`, `MAT_KHAU`) VALUES
('daominhphuong', 3, 'Đào Minh Phương', '2025-01-28', 'Long An', 'dmphuong@gmail.com', '0909300060', '$2y$10$q953nSwLjLr9eZod9MdxJ.HyKgGAAmesUJlKSIUM5ec7wpQLBUOiO'),
('doanminhdat', 3, 'Đoàn Minh Đạt ', '2025-04-16', 'Sóc Trăng', 'minhdat@gmail.com', '0907814560', '$2y$10$TNHas8Zv2.HV4lpZpGgRl.iBrX0q6y0amH.InLIY9vhBDmC362u32'),
('doantrongnghia', NULL, 'Đoàn Trọng Nghĩa', '2025-04-09', 'Cần Thơ', 'doantrongnghia@gmail.com', '0909300040', NULL),
('dohonganh', 3, 'Đỗ Hồng Anh', '2025-04-07', '123 Nguyễn Văn Linh, Ninh Kiều, Cần Thơ', 'kensobollyet@gmail.com', '0907814560', '$2y$10$vkp.QvpTnx4bOunRXtSFHuYdJPIlvtjbiHC7nPlXX6Vuk0Zdu.sWm'),
('ggnhivo357', 3, 'Nhi Hồng', NULL, NULL, 'nhivo3573@gmail.com', NULL, '$2y$10$nWOn1yQkl.8jznDQO1UCO.O119t.opxBtZGyvy19UR3eZe/jumCdy'),
('huynhphu', 3, 'Huỳnh Phú', NULL, 'sóc trăng', 'huynhphu@gmail.com', '0123324567', '$2y$10$fUxq.Q7QbpSyVdENk4sk.Oe97OBcaa8b9nMi7q1cH9LcM/Q0aVYla'),
('huynhtuankiet', 3, 'Huỳnh Tuấn Kiệt', '2025-04-01', 'Kiên Giang', 'htkiet@gmail.com', '0782952433', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyentrungkien', 3, 'Nguyễn Trung Kiên', '2025-04-01', 'Cà Mau', 'ntkien@gmail.com', '0788234234', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyenvietcuong', 3, 'Nguyễn Viết Cường', '2025-04-01', 'Bạc Liêu', 'nvcuong@gmail.com', '0909145765', '$2y$10$uqE8lWUMX/4DZMZHPxwjp.DCD7xSdHeDnUwzZpdWA/E6n.W5VPl/.'),
('phanthioanh', 3, 'Phan Thị Oanh', '1953-10-09', 'ấp Thiện Nhơn, Xã Thuận Hưng, huyện Mỹ tú, tỉnh Sóc Trăng', 'phanthioanh@gmail.com', '0907814560', '$2y$10$fUSCLq9HaI13vSf.ZYX14u9JFWCHxUzkrXe3a7WAvE.1dzNtPK4Je'),
('thuannguyen', 3, 'Nguyễn Văn Thuần', '2025-11-13', 'abc', 'thuannguyen@gmail.com', '0123456789', '$2y$10$6CKZAD24O8K4F4tilJCWVO9ng7Fm8ad.CObMbh/d5cLfunlsBm2eu'),
('trongnghia', 3, 'Trọng Nghĩa', '2003-01-21', 'phường 4, Thành phố Sóc Trăng', 'trongnghiann4911@gmail.com', '0909300040', '$2y$10$0.l3tXvqnN9AeV0RuNeogeXgugV5IqDmY7M7yp8t66ecqdTD.HFCO');

-- --------------------------------------------------------

--
-- Table structure for table `lich_bao_tri_thiet_bi`
--

CREATE TABLE `lich_bao_tri_thiet_bi` (
  `ID_BTTB` int(11) NOT NULL,
  `ID_TB` int(11) NOT NULL,
  `START_AT` datetime NOT NULL,
  `END_AT` datetime NOT NULL,
  `MO_TA` varchar(255) DEFAULT NULL,
  `TRANG_THAI` enum('len_ke_hoach','dang_bao_tri','hoan_tat') DEFAULT 'len_ke_hoach'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lich_hen`
--

CREATE TABLE `lich_hen` (
  `ID_LICHHEN` int(11) NOT NULL,
  `ID_DV` int(11) NOT NULL,
  `ID_GOI` int(11) DEFAULT NULL,
  `ID_TK` varchar(20) NOT NULL,
  `THOI_GIAN_BAT_DAU` datetime NOT NULL,
  `LOCATION_TYPE` enum('branch','external') NOT NULL DEFAULT 'branch',
  `LOCATION_ADDRESS` varchar(255) DEFAULT NULL,
  `LOCATION_LAT` decimal(10,7) DEFAULT NULL,
  `LOCATION_LNG` decimal(10,7) DEFAULT NULL,
  `DISTANCE_KM` decimal(8,2) DEFAULT NULL,
  `TRAVEL_FEE` int(11) DEFAULT NULL,
  `DIA_CHI_HEN` longtext DEFAULT NULL,
  `TRANGTHAI` varchar(20) DEFAULT NULL,
  `ID_CHINHANH` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `lich_hen`
--

INSERT INTO `lich_hen` (`ID_LICHHEN`, `ID_DV`, `ID_GOI`, `ID_TK`, `THOI_GIAN_BAT_DAU`, `LOCATION_TYPE`, `LOCATION_ADDRESS`, `LOCATION_LAT`, `LOCATION_LNG`, `DISTANCE_KM`, `TRAVEL_FEE`, `DIA_CHI_HEN`, `TRANGTHAI`, `ID_CHINHANH`) VALUES
(38, 8, NULL, 'dohonganh', '2025-04-19 09:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'chi nhánh Hà Nội', 'Đã hoàn thành', 1),
(39, 9, NULL, 'nguyenvietcuong', '2025-04-16 15:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'công viên Tao Đàn', 'Đã hoàn thành', 2),
(40, 9, NULL, 'daominhphuong', '2025-04-23 10:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'Nhà riêng, 12b2 KDC 30, Nguyễn Văn Linh, Hưng Lợi', 'Đã hoàn thành', 5),
(41, 1, NULL, 'nguyentrungkien', '2025-04-30 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'tại studio', 'Đã hoàn thành', 4),
(43, 7, NULL, 'trongnghia', '2025-04-19 10:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'tại studio', 'Đã hoàn thành', 1),
(44, 4, NULL, 'trongnghia', '2025-04-19 13:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'tại studio', 'Đã hoàn thành', 5),
(50, 2, NULL, 'trongnghia', '2025-04-20 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'abc', 'Đã xác nhận', 5),
(51, 1, NULL, 'trongnghia', '2025-04-20 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'acc', 'Đã xác nhận', 3),
(53, 9, NULL, 'dohonganh', '2025-04-30 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'Vinh độc lập', 'Đã xác nhận', 2),
(54, 9, NULL, 'dohonganh', '2025-04-30 09:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'Bến cảng nhà rồng', 'Đã xác nhận', 2),
(55, 6, NULL, 'dohonganh', '2025-04-30 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'Hồ Gươm', 'Đã xác nhận', 1),
(57, 9, NULL, 'dohonganh', '2025-04-30 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'ctu', 'Đã hoàn thành', 5),
(58, 2, NULL, 'trongnghia', '2025-10-26 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, '', 'Đã hủy', 2),
(59, 9, NULL, 'dohonganh', '2025-10-28 15:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'tại chi nhánh', 'Đã xác nhận', 2),
(60, 2, NULL, 'dohonganh', '2025-10-28 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'a', 'Đã xác nhận', 2),
(61, 1, NULL, 'dohonganh', '2025-10-28 09:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'aa', 'Đã xác nhận', 2),
(63, 8, NULL, 'ggnhivo357', '2025-11-19 08:00:00', 'branch', NULL, NULL, NULL, NULL, NULL, 'tại studio', 'Đã xác nhận', 5),
(64, 7, NULL, 'ggnhivo357', '2025-11-20 08:00:00', 'external', 'công viên sông hậu', 9.9964420, 105.6098500, 15.95, 0, 'công viên sông hậu', 'Đã hủy', 5),
(65, 25, NULL, 'ggnhivo357', '2025-11-21 09:00:00', 'external', 'Chi nhánh Cần Thơ', 10.0152520, 105.7410090, 3.39, 0, 'Chi nhánh Cần Thơ', 'Đã hủy', 5),
(66, 7, NULL, 'ggnhivo357', '2025-11-21 09:00:00', 'external', 'Chi nhánh Hà Nội', 21.0436870, 105.8498690, 2.41, 0, 'Chi nhánh Hà Nội', 'Đã hủy', 1),
(68, 25, NULL, 'ggnhivo357', '2025-11-21 08:00:00', 'external', '30 Tháng 4, Phường Phú Lợi, Thành phố Cần Thơ, 96113, Việt Nam', 9.6025130, 105.9730910, 55.11, 180000, '30 Tháng 4, Phường Phú Lợi, Thành phố Cần Thơ, 96113, Việt Nam', 'Đang chờ', 5),
(69, 2, 5, 'trongnghia', '2025-11-21 09:00:00', 'branch', 'Chi nhánh 5', NULL, NULL, NULL, 0, 'Chi nhánh Cần Thơ', 'Đang chờ', 5);

-- --------------------------------------------------------

--
-- Table structure for table `lich_lam_viec_nhan_vien`
--

CREATE TABLE `lich_lam_viec_nhan_vien` (
  `ID_LLV` int(11) NOT NULL,
  `ID_TK_NV` varchar(20) NOT NULL,
  `START_AT` datetime NOT NULL,
  `END_AT` datetime NOT NULL,
  `LOAI` enum('ca_lam','nghi_phep','dao_tao','khac') DEFAULT 'ca_lam',
  `GHI_CHU` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `luong_nhan_vien`
--

CREATE TABLE `luong_nhan_vien` (
  `ID_LUONG` int(11) NOT NULL,
  `ID_TK` varchar(20) NOT NULL,
  `THANG` int(11) NOT NULL,
  `NAM` int(11) NOT NULL,
  `LUONG_CO_BAN` int(11) NOT NULL DEFAULT 5000000,
  `PHAN_TRAM_THUONG` float DEFAULT 0.15,
  `TONG_TIEN_THUONG` int(11) DEFAULT 0,
  `TONG_LUONG` int(11) NOT NULL,
  `NGAY_TINH` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `luong_nhan_vien`
--

INSERT INTO `luong_nhan_vien` (`ID_LUONG`, `ID_TK`, `THANG`, `NAM`, `LUONG_CO_BAN`, `PHAN_TRAM_THUONG`, `TONG_TIEN_THUONG`, `TONG_LUONG`, `NGAY_TINH`) VALUES
(224, 'chauthimyhanh', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(225, 'dangthibichthao', 4, 2025, 5000000, 0.15, 15000, 5015000, '2025-04-23'),
(226, 'doanxuannghi', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(227, 'ltdiem', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(228, 'vohongnhi', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(229, 'vovanvu', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(230, 'chauthimyhanh', 3, 2025, 5000000, 0.15, 750000, 5750000, '2025-04-19'),
(231, 'taomanhduc', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(232, 'chauquocvinh', 4, 2025, 5000000, 0.15, 0, 5000000, '2025-04-23'),
(233, 'trongnghia01', 4, 2025, 5000000, 0.15, 105000, 5105000, '2025-04-23'),
(234, 'chauquocvinh', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(235, 'chauthimyhanh', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(236, 'doanxuannghi', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(237, 'ltdiem', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(238, 'vohongnhi', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(239, 'taomanhduc', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(240, 'dangthibichthao', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(241, 'trongnghia01', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(242, 'vovanvu', 11, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(243, 'chauquocvinh', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(244, 'chauthimyhanh', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(245, 'doanxuannghi', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(246, 'ltdiem', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(247, 'vohongnhi', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(248, 'taomanhduc', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(249, 'dangthibichthao', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(250, 'trongnghia01', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19'),
(251, 'vovanvu', 1, 2025, 5000000, 0.15, 0, 5000000, '2025-11-19');

-- --------------------------------------------------------

--
-- Table structure for table `nghi_phep_nhan_vien`
--

CREATE TABLE `nghi_phep_nhan_vien` (
  `ID_NP` int(11) NOT NULL,
  `ID_TK_NV` varchar(20) NOT NULL,
  `TU_NGAY` datetime NOT NULL,
  `DEN_NGAY` datetime NOT NULL,
  `LY_DO` varchar(255) DEFAULT NULL,
  `TRANG_THAI` enum('cho_duyet','da_duyet','tu_choi') DEFAULT 'cho_duyet',
  `NGUOI_DUYET` varchar(20) DEFAULT NULL,
  `NGAY_DUYET` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nhan_vien`
--

CREATE TABLE `nhan_vien` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `ID_QUYEN` int(11) DEFAULT 3,
  `HO_TEN` varchar(50) DEFAULT NULL,
  `NGAY_SINH` date DEFAULT NULL,
  `DIA_CHI` varchar(100) DEFAULT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `SDT` varchar(12) DEFAULT NULL,
  `MAT_KHAU` varchar(255) DEFAULT NULL,
  `CHUYEN_MON` varchar(100) DEFAULT NULL,
  `LOAI_NV` enum('chuyen_trach','quan_ly') NOT NULL DEFAULT 'chuyen_trach'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `nhan_vien`
--

INSERT INTO `nhan_vien` (`ID_TK`, `ID_CN`, `ID_QUYEN`, `HO_TEN`, `NGAY_SINH`, `DIA_CHI`, `EMAIL`, `SDT`, `MAT_KHAU`, `CHUYEN_MON`, `LOAI_NV`) VALUES
('chauquocvinh', 1, 3, NULL, NULL, NULL, NULL, NULL, NULL, 'Cameraman', 'chuyen_trach'),
('chauthimyhanh', 1, 2, 'Châu Thị Mỹ Hạnh', '2024-12-03', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814566', '$2y$10$GkLHsgeQUM2JzCtU16qSTeLNdNZnJVS.Y5TyLZSwDOvLorFkUEgTO', 'Chăm sóc khách hàng', 'chuyen_trach'),
('dangthibichthao', 5, 2, 'Đặng Thị Bích Thảo', '1983-06-23', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'dangthibichthao@gmail.com', '0909300040', '$2y$10$ofQ9Dz3ClNER5xzVYWAufO9FlTSwLm4RSHnToY0IkImQXGoGsYwGe', 'Chuyên viên make-up', 'quan_ly'),
('doanxuannghi', 1, 2, 'Đoàn Xuân Nghi', '2024-12-24', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814560', '$2y$10$FQE1w/A.Ep/jPC/bKo8sLu0xsSo7jXQT7kRNreUTV2z/NMnfh3a4O', 'Tư vấn viên', 'chuyen_trach'),
('ltdiem', 1, 2, 'Lê Thị Diễm', '1989-07-04', 'Vĩnh Long', 'nghia@gmail.com', '0939539596', '$2y$10$n9qV/5lQvy6rvBYh/5HzCOH7L9jcjYH6FwFE92ts8W1IRIGAe1ReO', 'Tư vấn viên', 'chuyen_trach'),
('taomanhduc', 4, 3, NULL, NULL, NULL, NULL, NULL, NULL, 'Gác Cổng', 'quan_ly'),
('trongnghia01', 5, 3, NULL, NULL, NULL, NULL, NULL, NULL, 'abc', 'chuyen_trach'),
('vohongnhi', 1, 2, 'Võ Hồng Nhi', '2024-12-01', 'Mỹ Tú', 'hongnhi@gmail.com', '0782952479', '$2y$10$I/FONmMp7GZclDtTxoj8oO9I4cmdWp0.YjC9Rz/Zv3hF3iqLpQ4mS', 'Nhiếp ảnh gia', 'chuyen_trach'),
('vovanvu', 5, 2, 'Võ Văn Vũ', '1974-07-12', 'Thị trấn Huỳnh Hũu Nghĩa, Mỹ Tú', 'vovanvu@gmai.com', '0782952479', '$2y$10$oADQTCkpU22I.K8psKPyVenK3unpIrYwGp89hZWFqOAKWWQqkFiPu', 'Giám đốc chi nhánh Cần Thơ', 'chuyen_trach');

-- --------------------------------------------------------

--
-- Table structure for table `nhat_ky_he_thong`
--

CREATE TABLE `nhat_ky_he_thong` (
  `ID_LOG` bigint(20) NOT NULL,
  `ACTOR_ID` varchar(20) DEFAULT NULL,
  `VAI_TRO` varchar(50) DEFAULT NULL,
  `HANH_DONG` varchar(100) NOT NULL,
  `DOI_TUONG` varchar(100) DEFAULT NULL,
  `TRUOC_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`TRUOC_JSON`)),
  `SAU_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`SAU_JSON`)),
  `IP` varchar(45) DEFAULT NULL,
  `USER_AGENT` varchar(255) DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `nhat_ky_he_thong`
--

INSERT INTO `nhat_ky_he_thong` (`ID_LOG`, `ACTOR_ID`, `VAI_TRO`, `HANH_DONG`, `DOI_TUONG`, `TRUOC_JSON`, `SAU_JSON`, `IP`, `USER_AGENT`, `CREATED_AT`) VALUES
(1, 'captianbidu', 'admin', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"ID_QUYEN\":1,\"role\":\"admin\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-18 21:47:54'),
(2, 'ggnhivo357', 'customer', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"ID_QUYEN\":3,\"role\":\"customer\",\"method\":\"google_oauth\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-18 22:29:47'),
(3, 'trongnghia01', 'staff', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"trongnghia01\",\"ID_QUYEN\":2,\"role\":\"staff\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-18 23:58:04'),
(4, 'captianbidu', 'admin', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"ID_QUYEN\":1,\"role\":\"admin\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-18 23:58:22'),
(5, 'trongnghia01', 'staff', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"trongnghia01\",\"ID_QUYEN\":2,\"role\":\"staff\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 10:37:37'),
(6, 'dangthibichthao', 'branch_manager', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"dangthibichthao\",\"ID_QUYEN\":2,\"role\":\"branch_manager\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 10:53:04'),
(7, 'captianbidu', 'admin', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"ID_QUYEN\":1,\"role\":\"admin\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 11:05:43'),
(8, NULL, NULL, 'LOGIN_FAILED', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"reason\":\"wrong_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 18:11:24'),
(9, NULL, NULL, 'LOGIN_FAILED', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"reason\":\"wrong_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 18:11:46'),
(10, 'captianbidu', 'admin', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"captianbidu\",\"ID_QUYEN\":1,\"role\":\"admin\",\"method\":\"local_form\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 18:11:48'),
(11, 'ggnhivo357', 'customer', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"ID_QUYEN\":3,\"role\":\"customer\",\"method\":\"google_oauth\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-19 19:11:17'),
(12, 'ggnhivo357', 'customer', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"ID_QUYEN\":3,\"role\":\"customer\",\"method\":\"google_oauth\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 15:32:29'),
(13, 'ggnhivo357', 'customer', 'LOGOUT', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"role\":\"customer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 15:50:26'),
(14, 'ggnhivo357', 'customer', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"ID_QUYEN\":3,\"role\":\"customer\",\"method\":\"google_oauth\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 15:50:34'),
(15, 'ggnhivo357', 'customer', 'LOGOUT', 'auth', NULL, '{\"ID_TK\":\"ggnhivo357\",\"role\":\"customer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 19:54:43'),
(16, 'trongnghia', 'customer', 'LOGIN_SUCCESS', 'auth', NULL, '{\"ID_TK\":\"trongnghia\",\"ID_QUYEN\":3,\"role\":\"customer\",\"method\":\"google_oauth\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 19:55:25'),
(17, 'captianbidu', 'admin', 'PACKAGE_BULK_UPDATE', 'package:10', NULL, '{\"count\":3}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-20 22:10:12');

-- --------------------------------------------------------

--
-- Table structure for table `phan_cong_nhan_vien`
--

CREATE TABLE `phan_cong_nhan_vien` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_LICHHEN` int(11) NOT NULL,
  `THOI_GIAN_BAT_DAU` datetime DEFAULT NULL,
  `THOI_GIAN_KET_THUC` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `phan_cong_nhan_vien`
--

INSERT INTO `phan_cong_nhan_vien` (`ID_TK`, `ID_LICHHEN`, `THOI_GIAN_BAT_DAU`, `THOI_GIAN_KET_THUC`) VALUES
('chauthimyhanh', 38, '2025-04-19 09:00:00', '2025-04-19 09:00:00'),
('chauthimyhanh', 55, '2025-04-30 08:00:00', '2025-04-30 08:00:00'),
('dangthibichthao', 40, '2025-04-23 10:00:00', '2025-04-25 10:00:00'),
('dangthibichthao', 50, '2025-04-20 08:00:00', '2025-04-20 08:00:00'),
('trongnghia01', 57, '2025-04-30 08:00:00', '2025-04-30 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `phan_hoi_cua_khach_hang`
--

CREATE TABLE `phan_hoi_cua_khach_hang` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_DV` int(11) NOT NULL,
  `NOI_DUNG` varchar(500) DEFAULT NULL,
  `NGAY_GUI` datetime DEFAULT NULL,
  `XEP_HANG_DV` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `phan_hoi_cua_khach_hang`
--

INSERT INTO `phan_hoi_cua_khach_hang` (`ID_TK`, `ID_DV`, `NOI_DUNG`, `NGAY_GUI`, `XEP_HANG_DV`) VALUES
('dohonganh', 8, 'rất tốt!!💕💕😂', '2025-04-23 02:30:17', 5),
('trongnghia', 4, 'aaaaaaaaaa', '2025-04-19 20:39:25', 5),
('trongnghia', 7, 'bbbbbbbbbbbbbbbbb', '2025-04-19 20:39:33', 5);

-- --------------------------------------------------------

--
-- Table structure for table `phu_phi_thue_trang_phuc`
--

CREATE TABLE `phu_phi_thue_trang_phuc` (
  `ID_PHI` int(11) NOT NULL,
  `ID_TTP` int(11) NOT NULL,
  `LOAI` enum('tre_han','hu_hong','giat_ui','khac') NOT NULL,
  `SO_TIEN` int(11) NOT NULL,
  `MO_TA` varchar(255) DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quang_tri_vien`
--

CREATE TABLE `quang_tri_vien` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_QUYEN` int(11) DEFAULT NULL,
  `HO_TEN` varchar(50) DEFAULT NULL,
  `NGAY_SINH` date DEFAULT NULL,
  `DIA_CHI` varchar(100) DEFAULT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `SDT` varchar(12) DEFAULT NULL,
  `MAT_KHAU` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quanly_luong_chinhanh`
--

CREATE TABLE `quanly_luong_chinhanh` (
  `ID` int(11) NOT NULL,
  `ID_TK_NV` varchar(20) NOT NULL,
  `ID_TK_MANAGER` varchar(20) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `THANG` int(11) NOT NULL,
  `NAM` int(11) NOT NULL,
  `BASE_TONG_LUONG` int(11) NOT NULL DEFAULT 0,
  `PHU_CAP` int(11) NOT NULL DEFAULT 0,
  `KHOAN_TRU` int(11) NOT NULL DEFAULT 0,
  `THUC_LINH` int(11) NOT NULL DEFAULT 0,
  `TRANG_THAI` enum('cho_duyet','da_duyet','da_chi_tra') NOT NULL DEFAULT 'cho_duyet',
  `GHI_CHU` text DEFAULT NULL,
  `NGAY_CAP_NHAT` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `NGAY_CHI_TRA` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quyen_truy_cap`
--

CREATE TABLE `quyen_truy_cap` (
  `ID_QUYEN` int(11) NOT NULL,
  `TEN_QUYEN` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `quyen_truy_cap`
--

INSERT INTO `quyen_truy_cap` (`ID_QUYEN`, `TEN_QUYEN`) VALUES
(1, 'Quản trị viên'),
(2, 'Nhân viên'),
(3, 'Khách hàng');

-- --------------------------------------------------------

--
-- Table structure for table `tai_chinh`
--

CREATE TABLE `tai_chinh` (
  `ID_TC` int(11) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `LOAI_GIAO_DICH` varchar(50) DEFAULT NULL,
  `LOAI_CHI_TIET` varchar(100) DEFAULT NULL,
  `SO_TIEN` decimal(10,0) DEFAULT NULL,
  `NGAY_GIAO_DICH` datetime DEFAULT NULL,
  `ID_HD` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `tai_chinh`
--

INSERT INTO `tai_chinh` (`ID_TC`, `ID_CN`, `LOAI_GIAO_DICH`, `LOAI_CHI_TIET`, `SO_TIEN`, `NGAY_GIAO_DICH`, `ID_HD`) VALUES
(1, 4, 'doanh thu', NULL, 1200000, '2025-04-14 04:22:57', 27),
(2, 3, 'doanh thu', NULL, 275000, '2025-04-14 17:29:32', 19),
(3, 5, 'doanh thu', NULL, 265000, '2025-04-16 16:50:54', 26),
(7, 4, 'chi phí', NULL, 20000, '2025-04-01 00:00:00', NULL),
(8, 4, 'chi phí', NULL, 300000, '2025-04-01 00:00:00', NULL),
(9, 4, 'chi phí', NULL, 120000, '2025-04-01 00:00:00', NULL),
(19, 5, 'chi phí', NULL, 3333333, '2025-04-01 00:00:00', NULL),
(20, 5, 'chi phí', NULL, 4444444, '2025-04-01 00:00:00', NULL),
(21, 5, 'chi phí', NULL, 26500, '2025-04-01 00:00:00', NULL),
(22, 4, 'doanh thu', NULL, 2500000, '2025-04-18 12:45:04', 25),
(23, 5, 'doanh thu', NULL, 1200000, '2025-04-18 12:58:35', 23),
(24, 4, 'doanh thu', NULL, 1200000, '2025-04-18 12:58:42', 17),
(49, 5, 'chi phí', 'lương nhân viên', 5000000, '2025-04-18 00:00:00', NULL),
(53, 5, 'chi phí', 'lương nhân viên', 5000000, '2025-04-18 00:00:00', NULL),
(64, 3, 'chi phí', NULL, 400000, '2025-04-01 00:00:00', NULL),
(65, 3, 'chi phí', NULL, 3333333, '2025-04-01 00:00:00', NULL),
(66, 3, 'chi phí', NULL, 27500, '2025-04-01 00:00:00', NULL),
(67, 4, 'chi phí', 'lương nhân viên', 5000000, '2025-04-20 00:00:00', NULL),
(69, 5, 'doanh thu', NULL, 200000, '2025-04-20 04:37:28', 34),
(70, 2, 'doanh thu', NULL, 75000, '2025-04-20 04:37:34', 32),
(71, 4, 'chi phí', 'thiết bị', 524924, '2023-07-18 00:00:00', NULL),
(72, 5, 'doanh thu', NULL, 935701, '2023-09-20 00:00:00', 7),
(73, 2, 'doanh thu', NULL, 788225, '2023-09-28 00:00:00', 23),
(74, 3, 'chi phí', 'marketing', 860413, '2023-10-10 00:00:00', NULL),
(75, 2, 'chi phí', 'lương nhân viên', 1810031, '2024-08-01 00:00:00', NULL),
(76, 2, 'chi phí', 'lương nhân viên', 604748, '2024-05-03 00:00:00', NULL),
(77, 4, 'chi phí', 'lương nhân viên', 152380, '2024-12-06 00:00:00', NULL),
(78, 2, 'doanh thu', NULL, 1405562, '2024-02-26 00:00:00', 5),
(79, 2, 'doanh thu', NULL, 883027, '2023-08-05 00:00:00', 21),
(80, 3, 'doanh thu', NULL, 543117, '2024-10-17 00:00:00', 16),
(81, 1, 'doanh thu', NULL, 2903775, '2024-07-25 00:00:00', 21),
(82, 2, 'doanh thu', NULL, 2125223, '2023-05-10 00:00:00', 2),
(83, 2, 'chi phí', 'thiết bị', 2357135, '2024-12-10 00:00:00', NULL),
(84, 4, 'chi phí', 'dịch vụ', 408554, '2024-09-29 00:00:00', NULL),
(85, 5, 'doanh thu', NULL, 2384019, '2024-03-07 00:00:00', 10),
(86, 3, 'chi phí', NULL, 659793, '2023-03-17 00:00:00', NULL),
(87, 4, 'doanh thu', NULL, 2544291, '2024-05-25 00:00:00', 27),
(88, 5, 'chi phí', NULL, 1316058, '2023-11-29 00:00:00', NULL),
(89, 1, 'chi phí', 'thuê mặt bằng', 305496, '2023-12-16 00:00:00', NULL),
(90, 1, 'doanh thu', NULL, 2603987, '2023-06-19 00:00:00', 3),
(91, 2, 'chi phí', 'marketing', 651238, '2024-11-22 00:00:00', NULL),
(92, 4, 'doanh thu', NULL, 2824895, '2024-06-09 00:00:00', 29),
(93, 2, 'chi phí', 'marketing', 596832, '2024-03-29 00:00:00', NULL),
(94, 3, 'doanh thu', NULL, 2439851, '2023-02-05 00:00:00', 14),
(95, 1, 'doanh thu', NULL, 1428491, '2024-12-26 00:00:00', 21),
(96, 5, 'doanh thu', NULL, 2028799, '2025-06-24 00:00:00', 8),
(97, 5, 'doanh thu', NULL, 2277129, '2025-02-11 00:00:00', 27),
(98, 4, 'doanh thu', NULL, 1457043, '2025-01-20 00:00:00', 25),
(99, 3, 'doanh thu', NULL, 906103, '2025-03-10 00:00:00', 8),
(100, 3, 'doanh thu', NULL, 2249282, '2025-01-27 00:00:00', 29),
(101, 1, 'doanh thu', NULL, 1939788, '2025-02-20 00:00:00', 13),
(102, 3, 'doanh thu', NULL, 1944317, '2025-04-05 00:00:00', 9),
(103, 3, 'chi phí', NULL, 716587, '2025-03-13 00:00:00', NULL),
(104, 5, 'chi phí', 'marketing', 256386, '2025-03-07 00:00:00', NULL),
(105, 3, 'chi phí', 'thiết bị', 1190862, '2025-06-21 00:00:00', NULL),
(106, 1, 'doanh thu', NULL, 1488454, '2025-03-24 00:00:00', 15),
(107, 4, 'chi phí', NULL, 983386, '2025-01-22 00:00:00', NULL),
(108, 1, 'chi phí', 'thiết bị', 1142552, '2025-02-23 00:00:00', NULL),
(109, 5, 'chi phí', 'marketing', 1998863, '2025-03-04 00:00:00', NULL),
(110, 2, 'doanh thu', NULL, 2067022, '2025-04-04 00:00:00', 20),
(111, 3, 'doanh thu', NULL, 2360680, '2025-01-25 00:00:00', 29),
(112, 1, 'doanh thu', NULL, 2140564, '2025-03-05 00:00:00', 13),
(113, 5, 'chi phí', 'thiết bị', 171535, '2025-02-21 00:00:00', NULL),
(114, 4, 'chi phí', 'marketing', 1071093, '2025-03-22 00:00:00', NULL),
(115, 1, 'doanh thu', NULL, 878314, '2025-02-09 00:00:00', 28),
(116, 3, 'doanh thu', NULL, 1200000, '2025-04-21 01:47:36', 35),
(131, 1, 'chi phí', NULL, 2, '2025-04-01 00:00:00', NULL),
(132, 1, 'chi phí', NULL, 2, '2025-04-01 00:00:00', NULL),
(133, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-04-22 00:00:00', NULL),
(134, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-04-22 00:00:00', NULL),
(135, 1, 'chi phí', 'lương nhân viên', 5030000, '2025-04-22 00:00:00', NULL),
(136, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-04-22 00:00:00', NULL),
(137, 5, 'doanh thu', NULL, 4000000, '2025-04-23 16:39:18', 41),
(138, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(139, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(140, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(141, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(142, 1, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(143, 4, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(144, 5, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(145, 5, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(146, 5, 'chi phí', 'lương nhân viên', 5000000, '2025-11-05 00:00:00', NULL),
(147, 1, 'doanh thu', NULL, 600000, '2025-11-19 00:06:47', 39),
(148, 2, 'doanh thu', NULL, 2400000, '2025-11-19 00:06:59', 38),
(149, 2, 'doanh thu', NULL, 2400000, '2025-11-19 00:07:07', 37),
(150, 1, 'chi phí', 'lương nhân viên', 25000000, '2025-11-19 00:00:00', NULL),
(151, 4, 'chi phí', 'lương nhân viên', 5000000, '2025-11-19 00:00:00', NULL),
(152, 5, 'chi phí', 'lương nhân viên', 15000000, '2025-11-19 00:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tai_khoan`
--

CREATE TABLE `tai_khoan` (
  `ID_TK` varchar(20) NOT NULL,
  `ID_QUYEN` int(11) NOT NULL,
  `HO_TEN` varchar(50) DEFAULT NULL,
  `NGAY_SINH` date DEFAULT NULL,
  `DIA_CHI` varchar(100) DEFAULT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `SDT` varchar(12) DEFAULT NULL,
  `MAT_KHAU` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `tai_khoan`
--

INSERT INTO `tai_khoan` (`ID_TK`, `ID_QUYEN`, `HO_TEN`, `NGAY_SINH`, `DIA_CHI`, `EMAIL`, `SDT`, `MAT_KHAU`) VALUES
('captianbidu', 1, 'Captain Bidu', '2022-10-26', 'Cần Thơ', 'admin@gmail.com', '0907814560', '$2y$10$.tz36JZTjb8nPCKrVG3IHeq2DVFHKYiTKkPxp6eOOqSlnutPmP.Pe'),
('chauquocvinh', 2, 'Châu Quốc Vinh', '2025-04-09', 'Vĩnh Long', 'vinh@gmail.com', '0782345678', '$2y$10$trzVZbPOPbg7kuecz..J0eOj0.0rMBPHroHPdHdAyb7EP/vKzbE.y'),
('chauthimyhanh', 2, 'Châu Thị Mỹ Hạnh', '2024-12-03', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814566', '$2y$10$g0ZVia2OwMHPDW/0VMSohuReRVYAdcPj/C1vsJSk49vWCAAUJvVyq'),
('chauvanuot', 3, 'Châu Văn Uốt', '1955-06-11', 'Ấp Xóm Đồng 1, Huyện Kế Sách, Tỉnh Sóc Trăng', 'chauvanuot@gmail.com', '00', '$2y$10$fCjWC8brkgXmvAxrZbwXGOxwcQflOmf6XmH3pnYKGEWjT8HKgnQRW'),
('dangthibichthao', 2, 'Đặng Thị Bích Thảo', '1983-06-23', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'dangthibichthao@gmail.com', '0909300040', '$2y$10$bd8Xv8.Vjk4wjNb223kPDOfG8eh8GI3/Zh/LuhFhXR19J75hj.omy'),
('daominhphuong', 3, 'Đào Minh Phương', '2025-01-28', 'Long An', 'dmphuong@gmail.com', '0909300060', '$2y$10$q953nSwLjLr9eZod9MdxJ.HyKgGAAmesUJlKSIUM5ec7wpQLBUOiO'),
('doanminhdat', 3, 'Đoàn Minh Đạt ', '2025-04-16', 'Sóc Trăng', 'minhdat@gmail.com', '0907814560', '$2y$10$TNHas8Zv2.HV4lpZpGgRl.iBrX0q6y0amH.InLIY9vhBDmC362u32'),
('doantrongnghia', 3, 'Đoàn Trọng Nghĩa', '2025-04-09', 'Cần Thơ', 'doantrongnghia@gmail.com', '0909300040', '$2y$10$m5QJnVVSEWdSdEqCDCh2UemZhTUmT98gvvyk9jqgjxtsI7rOXjgnW'),
('doanxuannghi', 2, 'Đoàn Xuân Nghi', '2024-12-24', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814560', '$2y$10$FQE1w/A.Ep/jPC/bKo8sLu0xsSo7jXQT7kRNreUTV2z/NMnfh3a4O'),
('dohonganh', 3, 'Đỗ Hồng Anh', '2025-04-07', '123 Nguyễn Văn Linh, Ninh Kiều, Cần Thơ', 'kensobollyet@gmail.com', '0907814560', '$2y$10$6fWvJZZCznDFQMK3e0Q3Ne6M3n8.XcZ8APRiMB8FtrLDJ.3QmXWL6'),
('ggnhivo357', 3, 'Nhi Hồng', NULL, NULL, 'nhivo3573@gmail.com', NULL, '$2y$10$nWOn1yQkl.8jznDQO1UCO.O119t.opxBtZGyvy19UR3eZe/jumCdy'),
('huynhphu', 3, 'Huỳnh Phú', NULL, 'sóc trăng', 'huynhphu@gmail.com', '0123324567', '$2y$10$fUxq.Q7QbpSyVdENk4sk.Oe97OBcaa8b9nMi7q1cH9LcM/Q0aVYla'),
('huynhtuankiet', 3, 'Huỳnh Tuấn Kiệt', '2025-04-01', 'Kiên Giang', 'htkiet@gmail.com', '0782952433', '$2y$10$8eccDZkfgKJu/OFDPE0gquo.ivMRDsgSPoW51An97vKbTC1.9Ln/m'),
('ltdiem', 2, 'Lê Thị Diễm', '1989-07-04', 'Vĩnh Long', 'nghia@gmail.com', '0939539596', '$2y$10$cQDaO.GoDZCmuG8YJ5Lmi.2mM5WlCv850hrLA6xFQa2f5B6MTb57C'),
('nguyentrungkien', 3, 'Nguyễn Trung Kiên', '2025-04-01', 'Cà Mau', 'ntkien@gmail.com', '0788234234', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyenvietcuong', 3, 'Nguyễn Viết Cường', '2025-04-01', 'Bạc Liêu', 'nvcuong@gmail.com', '0909145765', '$2y$10$uqE8lWUMX/4DZMZHPxwjp.DCD7xSdHeDnUwzZpdWA/E6n.W5VPl/.'),
('phanthioanh', 3, 'Phan Thị Oanh', '1953-10-09', 'ấp Thiện Nhơn, Xã Thuận Hưng, huyện Mỹ tú, tỉnh Sóc Trăng', 'phanthioanh@gmail.com', '0907814560', '$2y$10$fUSCLq9HaI13vSf.ZYX14u9JFWCHxUzkrXe3a7WAvE.1dzNtPK4Je'),
('taomanhduc', 2, 'Tào Mạnh Đức', '2025-04-01', 'Chợ Lớn', 'taomanhduc@gmail.com', '0909731202', '$2y$10$o/GEiOa0OS8VU0S9Ql3vlubCEIQZvjlTldU9cjkF6ZZ39SWVZDeAS'),
('thuannguyen', 3, 'Nguyễn Văn Thuần', '2025-11-13', 'abc', 'thuannguyen@gmail.com', '0123456789', '$2y$10$6CKZAD24O8K4F4tilJCWVO9ng7Fm8ad.CObMbh/d5cLfunlsBm2eu'),
('trantuantu', 3, 'Trần Tuấn Tú', '1993-07-17', 'Mỹ Tú', 'trantuantu@gmail.com', '12', '$2y$10$u6j1NSYtKvu1neto.xawlOEGGlYzWNFEgBQgW1.242Ot0FZUP3ffy'),
('trongnghia', 3, 'Trọng Nghĩa', '2003-01-21', 'phường 4, Thành phố Sóc Trăng', 'trongnghiann4911@gmail.com', '0909300040', '$2y$10$0.l3tXvqnN9AeV0RuNeogeXgugV5IqDmY7M7yp8t66ecqdTD.HFCO'),
('trongnghia01', 2, 'Trong Nghia', '2025-04-09', 'Can Tho', 'trongnghia1@gmail.com', '0907814560', '$2y$10$D1B6koPd7rD2QiLEm/5.U.Fb9sRsHkCLPbZvu.uyAuorMAjSTQaQ6'),
('trongnghia03', 3, 'Đoàn Trọng Nghĩa', '2024-12-02', 'VĨnh Long', 'nghia@gmail.com', '0907814560', '$2y$10$OCwhJ/yRFqW1dezQKZIgv.oqmrXAsYHdYTielmVku.YzFeqd8F2me'),
('vohongnhi', 2, 'Võ Hồng Nhi', '2024-12-01', 'Mỹ Tú', 'hongnhi@gmail.com', '0782952479', '$2y$10$I/FONmMp7GZclDtTxoj8oO9I4cmdWp0.YjC9Rz/Zv3hF3iqLpQ4mS'),
('vovanvu', 2, 'Võ Văn Vũ', '1974-07-12', 'Thị trấn Huỳnh Hũu Nghĩa, Mỹ Tú', 'vovanvu@gmai.com', '0782952479', '$2y$10$oADQTCkpU22I.K8psKPyVenK3unpIrYwGp89hZWFqOAKWWQqkFiPu');

--
-- Triggers `tai_khoan`
--
DELIMITER $$
CREATE TRIGGER `update_khach_hang` AFTER UPDATE ON `tai_khoan` FOR EACH ROW BEGIN
    -- Cập nhật thông tin trong bảng KHACH_HANG sau khi bảng TAI_KHOAN thay đổi
    UPDATE KHACH_HANG
    SET HO_TEN = NEW.HO_TEN,
        NGAY_SINH = NEW.NGAY_SINH,
        DIA_CHI = NEW.DIA_CHI,
        EMAIL = NEW.EMAIL,
        SDT = NEW.SDT
    WHERE ID_TK = NEW.ID_TK;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `thanh_toan_truc_tuyen`
--

CREATE TABLE `thanh_toan_truc_tuyen` (
  `ID_TTTT` bigint(20) NOT NULL,
  `ID_HD` int(11) NOT NULL,
  `GATEWAY` varchar(50) NOT NULL,
  `MA_THAM_CHIEU` varchar(100) NOT NULL,
  `SO_TIEN` decimal(12,0) NOT NULL,
  `CURRENCY` varchar(10) DEFAULT 'VND',
  `TRANG_THAI` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  `RAW_CALLBACK` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`RAW_CALLBACK`)),
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `thanh_toan_truc_tuyen`
--

INSERT INTO `thanh_toan_truc_tuyen` (`ID_TTTT`, `ID_HD`, `GATEWAY`, `MA_THAM_CHIEU`, `SO_TIEN`, `CURRENCY`, `TRANG_THAI`, `RAW_CALLBACK`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 39, 'vnpay', 'HD39_20251106111158', 600000, 'VND', 'pending', NULL, '2025-11-06 17:11:58', NULL),
(2, 39, 'vnpay', 'HD39_20251106111227', 600000, 'VND', 'pending', NULL, '2025-11-06 17:12:27', NULL),
(3, 38, 'vnpay', 'HD38_20251114191245', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:12:45', NULL),
(4, 38, 'vnpay', 'HD38_20251114193809', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:38:09', NULL),
(5, 38, 'vnpay', 'HD38_20251114193831', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:38:31', NULL),
(6, 39, 'vnpay', 'HD39_20251114194221', 600000, 'VND', 'pending', NULL, '2025-11-15 01:42:21', NULL),
(7, 39, 'vnpay', 'HD39_20251114194328', 600000, 'VND', 'pending', NULL, '2025-11-15 01:43:28', NULL),
(8, 37, 'vnpay', 'HD37_20251114194343', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:43:43', NULL),
(9, 37, 'vnpay', 'HD37_20251114194353', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:43:53', NULL),
(10, 37, 'vnpay', 'HD37_20251114194622', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:46:22', NULL),
(11, 38, 'vnpay', 'HD38_20251114194847', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:48:47', NULL),
(12, 37, 'vnpay', 'HD37_20251114194854', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:48:54', NULL),
(13, 37, 'vnpay', 'HD37_20251114195107', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:51:07', NULL),
(14, 37, 'vnpay', 'HD37_20251114195508', 2400000, 'VND', 'pending', NULL, '2025-11-15 01:55:08', NULL),
(15, 37, 'vnpay', 'HD37_20251114201218', 2400000, 'VND', 'pending', NULL, '2025-11-15 02:12:18', NULL),
(16, 43, 'vnpay', 'HD43_20251115041915', 1200000, 'VND', 'pending', NULL, '2025-11-15 10:19:15', NULL),
(17, 42, 'vnpay', 'HD42_20251115041929', 200000, 'VND', 'pending', NULL, '2025-11-15 10:19:29', NULL),
(18, 43, 'vnpay', 'HD43_20251115052301', 1200000, 'VND', 'success', '{\"vnp_Amount\":\"120000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP15262591\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan hoa don 43\",\"vnp_PayDate\":\"20251115112527\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"VXTH6A54\",\"vnp_TransactionNo\":\"15262591\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"HD43_20251115052301\",\"vnp_SecureHash\":\"b8141fa700d37f54c2bde370f07dc8a33b9a14bfc1d7110b76d174e7bbc88586b098d776b96aa4d011fb32ab0c20a8ff7ab73815f437ab0de947ded895b955df\"}', '2025-11-15 11:23:01', '2025-11-15 11:25:44'),
(19, 39, 'vnpay', 'HD39_20251115053327', 600000, 'VND', 'pending', NULL, '2025-11-15 11:33:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `thoi_diem`
--

CREATE TABLE `thoi_diem` (
  `NGAY_GIO` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `thoi_diem`
--

INSERT INTO `thoi_diem` (`NGAY_GIO`) VALUES
('2024-10-01 10:00:00'),
('2024-10-02 11:00:00'),
('2024-10-03 12:00:00'),
('2024-10-04 13:00:00'),
('2024-10-05 14:00:00'),
('2025-03-30 14:42:31'),
('2025-03-30 14:44:24'),
('2025-04-17 21:40:46'),
('2025-04-17 21:55:52'),
('2025-04-18 07:11:49'),
('2025-04-18 07:12:10'),
('2025-04-18 07:12:33'),
('2025-04-18 07:12:44'),
('2025-04-18 07:13:49'),
('2025-04-20 20:52:14'),
('2025-04-20 20:53:22'),
('2025-04-20 20:54:21'),
('2025-04-20 20:54:59'),
('2025-04-20 20:55:18'),
('2025-04-21 18:34:57'),
('2025-04-21 18:35:08'),
('2025-04-23 02:18:26'),
('2025-04-23 02:31:18'),
('2025-04-23 02:32:54'),
('2025-04-23 02:34:20'),
('2025-04-23 02:41:57'),
('2025-04-23 02:54:13'),
('2025-04-23 03:01:27'),
('2025-04-23 08:02:53');

-- --------------------------------------------------------

--
-- Table structure for table `thong_bao`
--

CREATE TABLE `thong_bao` (
  `ID_TBThongBao` bigint(20) NOT NULL,
  `ID_TK_NGUOI_NHAN` varchar(20) NOT NULL,
  `LOAI` enum('email','sms','inapp') NOT NULL,
  `TIEU_DE` varchar(200) DEFAULT NULL,
  `NOI_DUNG` text DEFAULT NULL,
  `PAYLOAD_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`PAYLOAD_JSON`)),
  `TRANG_THAI_GUI` enum('queued','sent','failed') DEFAULT 'queued',
  `LAN_THU` int(11) DEFAULT 0,
  `SENT_AT` datetime DEFAULT NULL,
  `ERROR_MSG` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trang_phuc`
--

CREATE TABLE `trang_phuc` (
  `ID_TP` int(11) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `ID_LOAI` int(11) DEFAULT NULL,
  `TEN_TP` varchar(120) NOT NULL,
  `SIZE` varchar(20) DEFAULT NULL,
  `MAU` varchar(40) DEFAULT NULL,
  `TINH_TRANG` enum('san_sang','dang_thue','bao_tri','ngung') DEFAULT 'san_sang',
  `NGAY_GIAT_CUOI` date DEFAULT NULL,
  `GHI_CHU` varchar(255) DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `trang_phuc`
--

INSERT INTO `trang_phuc` (`ID_TP`, `ID_CN`, `ID_LOAI`, `TEN_TP`, `SIZE`, `MAU`, `TINH_TRANG`, `NGAY_GIAT_CUOI`, `GHI_CHU`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
(2, 5, 2, 'Áo dài truyền thống', 'M', 'Đỏ', 'san_sang', '2025-11-05', 'Áo dài truyền thống', 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(3, 3, 2, 'Áo dài truyền thống', 'M', 'Đỏ', 'san_sang', '2025-11-05', 'Áo dài truyền thống', 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(4, 1, 2, 'Áo dài truyền thống', 'M', 'Đỏ', 'san_sang', '2025-11-05', 'Áo dài truyền thống', 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(5, 4, 2, 'Áo dài truyền thống', 'M', 'Đỏ', 'san_sang', '2025-11-05', 'Áo dài truyền thống', 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(6, 2, 2, 'Áo dài truyền thống', 'M', 'Đỏ', 'san_sang', '2025-11-05', 'Áo dài truyền thống', 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(7, 5, 3, 'Nón da bò', 'M', 'Nâu', 'san_sang', '2025-11-05', 'Nón cao bồi nam', 1, '2025-11-05 02:18:42', '2025-11-05 02:18:42'),
(8, 5, 4, 'Cosplay Yasuo', 'M', 'Trắng-Đen', 'san_sang', NULL, NULL, 1, '2025-11-05 14:00:37', '2025-11-17 12:38:14');

-- --------------------------------------------------------

--
-- Table structure for table `trang_phuc_hinh_anh`
--

CREATE TABLE `trang_phuc_hinh_anh` (
  `ID_HA` int(11) NOT NULL,
  `ID_TP` int(11) NOT NULL,
  `URL` varchar(255) NOT NULL,
  `ALT_TEXT` varchar(255) DEFAULT NULL,
  `THU_TU` int(11) DEFAULT 1,
  `IS_COVER` tinyint(1) DEFAULT 0,
  `IS_ACTIVE` tinyint(1) DEFAULT 1,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `trang_phuc_hinh_anh`
--

INSERT INTO `trang_phuc_hinh_anh` (`ID_HA`, `ID_TP`, `URL`, `ALT_TEXT`, `THU_TU`, `IS_COVER`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
(2, 2, 'public/images/trangphuc/903c629b-dfd7-467c-87c7-f4799fdd8028_1762283443.png', 'Ảnh bìa trang phục Áo dài truyền thống', 1, 1, 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(3, 3, 'public/images/trangphuc/903c629b-dfd7-467c-87c7-f4799fdd8028_1762283443.png', 'Ảnh bìa trang phục Áo dài truyền thống', 1, 1, 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(4, 4, 'public/images/trangphuc/903c629b-dfd7-467c-87c7-f4799fdd8028_1762283443.png', 'Ảnh bìa trang phục Áo dài truyền thống', 1, 1, 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(5, 5, 'public/images/trangphuc/903c629b-dfd7-467c-87c7-f4799fdd8028_1762283443.png', 'Ảnh bìa trang phục Áo dài truyền thống', 1, 1, 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(6, 6, 'public/images/trangphuc/903c629b-dfd7-467c-87c7-f4799fdd8028_1762283443.png', 'Ảnh bìa trang phục Áo dài truyền thống', 1, 1, 1, '2025-11-05 02:10:43', '2025-11-05 02:10:43'),
(7, 7, 'public/images/trangphuc/pic_1__1762283922.png', 'Ảnh bìa trang phục Nón da bò', 1, 1, 1, '2025-11-05 02:18:42', '2025-11-05 02:18:42'),
(8, 8, 'public/images/trangphuc/which-of-these-has-the-best-wallpaper-potential-v0-sqgjyrf2g1yf1_1762326037.jpg', 'Ảnh bìa trang phục Cosplay Yasuo', 1, 1, 1, '2025-11-05 14:00:37', '2025-11-05 14:00:37'),
(9, 8, 'public/images/trangphuc/2b3z30mtcxxf1_1762326037.jpeg', 'Ảnh trang phục Cosplay Yasuo #1', 2, 0, 1, '2025-11-05 14:00:37', '2025-11-05 14:00:37'),
(10, 8, 'public/images/trangphuc/4rb5458w_1762326037.png', 'Ảnh trang phục Cosplay Yasuo #2', 3, 0, 1, '2025-11-05 14:00:37', '2025-11-05 14:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `trang_phuc_loai`
--

CREATE TABLE `trang_phuc_loai` (
  `ID_LOAI` int(11) NOT NULL,
  `TEN_LOAI` varchar(100) NOT NULL,
  `MOTA` varchar(255) DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `UPDATED_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `trang_phuc_loai`
--

INSERT INTO `trang_phuc_loai` (`ID_LOAI`, `TEN_LOAI`, `MOTA`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 'Váy cưới', 'váy cưới', 1, '2025-11-05 02:04:02', '2025-11-05 02:04:02'),
(2, 'Áo dài', 'Áo dài truyền thống', 1, '2025-11-05 02:09:21', '2025-11-05 02:09:21'),
(3, 'Nón Nam', 'Nón, mũ nam', 1, '2025-11-05 02:16:48', '2025-11-05 02:16:48'),
(4, 'Cosplay', '', 1, '2025-11-05 13:58:51', '2025-11-05 13:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `trang_thai_lich_hen_log`
--

CREATE TABLE `trang_thai_lich_hen_log` (
  `ID_LOG` int(11) NOT NULL,
  `ID_LICHHEN` int(11) NOT NULL,
  `TRANG_THAI_CU` varchar(20) DEFAULT NULL,
  `TRANG_THAI_MOI` varchar(20) NOT NULL,
  `THOI_DIEM` datetime NOT NULL DEFAULT current_timestamp(),
  `ID_TK_THUC_HIEN` varchar(20) DEFAULT NULL,
  `GHI_CHU` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trang_thiet_bi`
--

CREATE TABLE `trang_thiet_bi` (
  `ID_TB` int(11) NOT NULL,
  `ID_CN` int(11) NOT NULL,
  `TEN_TB` varchar(100) DEFAULT NULL,
  `TINH_TRANG` varchar(20) DEFAULT NULL,
  `NGAY_BAO_TRI` date DEFAULT NULL,
  `IMAGE` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `trang_thiet_bi`
--

INSERT INTO `trang_thiet_bi` (`ID_TB`, `ID_CN`, `TEN_TB`, `TINH_TRANG`, `NGAY_BAO_TRI`, `IMAGE`) VALUES
(1, 1, 'Máy ảnh Canon EOS R50', 'Đang hoạt động', '2024-01-10', '../../../public/images/trang_thietbi/mayanh.png'),
(2, 2, 'Máy Quay Canon NDV', 'Bảo trì', '2024-02-15', '../../../public/images/trang_thietbi/mayquay.jpg'),
(3, 3, 'Trọn bộ áo cưới ', 'Hoạt động', '2024-03-20', '../../../public/images/trang_thietbi/aocuoi.jpg'),
(4, 4, 'Trang phục cosplay 01', 'Hỏng', '2024-04-25', '../../../public/images/trang_thietbi/cosplay1.jpg'),
(5, 5, 'Trang phục cosplay 02', 'Hoạt động', '2024-05-30', '../../../public/images/trang_thietbi/cosplay2.jpg'),
(6, 2, 'Trang phục cosplay 03', 'Hoạt động', '0000-00-00', '../../../public/images/trang_thietbi/cosplay3.jpg'),
(7, 3, 'Trọn bộ âu phục nam', 'Hoạt động', '0000-00-00', '../../../public/images/trang_thietbi/suit.jpg'),
(8, 5, 'Máy ảnh cổ điển', 'Bảo trì', '0000-00-00', '../../../public/images/trang_thietbi/mayanh2.jpg'),
(9, 4, 'Gimbal ', 'Hoạt động', '0000-00-00', '../../../public/images/trang_thietbi/gimbal.jpg'),
(10, 1, 'Canon R6 Body', 'Đang hoạt động', '2025-04-20', './././public/images/trang_thietbi/canon_r6.jpg'),
(11, 1, 'Đèn LED Panel 60W + Softbox', 'Đang hoạt động', '2025-04-18', './././public/images/trang_thietbi/led60w.jpg');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dich_vu_gia_moinhat`
-- (See below for the actual view)
--
CREATE TABLE `v_dich_vu_gia_moinhat` (
`ID_DV` int(11)
,`TEN_DV` varchar(100)
,`MOTA_DV` varchar(500)
,`IMAGE` varchar(200)
,`THOI_GIAN` int(11)
,`GIA_MOI_NHAT` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_goi_dich_vu`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_goi_dich_vu` (
`ID_GOI` int(11)
,`ID_DV` int(11)
,`TEN_DV` varchar(100)
,`URL` varchar(255)
,`ALT_TEXT` varchar(255)
,`IS_COVER` tinyint(1)
,`THU_TU` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_goi_dich_vu_tong_tien`
-- (See below for the actual view)
--
CREATE TABLE `v_goi_dich_vu_tong_tien` (
`ID_GOI` int(11)
,`TEN_GOI` varchar(150)
,`MO_TA` varchar(500)
,`HINH_ANH` varchar(200)
,`HIEU_LUC_TU` datetime
,`HIEU_LUC_DEN` datetime
,`TRANG_THAI` enum('nhap','ban','ngung')
,`TONG_GIA_GOI` decimal(42,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_trang_phuc_don_gia_moinhat`
-- (See below for the actual view)
--
CREATE TABLE `v_trang_phuc_don_gia_moinhat` (
`ID_TP` int(11)
,`TEN_TP` varchar(120)
,`ID_CN` int(11)
,`SIZE` varchar(20)
,`MAU` varchar(40)
,`DON_GIA` int(11)
,`HIEU_LUC_TU` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `xac_nhan_hoan_thanh`
--

CREATE TABLE `xac_nhan_hoan_thanh` (
  `ID_XACNHAN` int(11) NOT NULL,
  `ID_LICHHEN` int(11) NOT NULL,
  `ID_TK` varchar(20) NOT NULL,
  `TRANGTHAI` enum('Đã xác nhận') DEFAULT 'Đã xác nhận',
  `THOI_GIAN_XAC_NHAN` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `xac_nhan_hoan_thanh`
--

INSERT INTO `xac_nhan_hoan_thanh` (`ID_XACNHAN`, `ID_LICHHEN`, `ID_TK`, `TRANGTHAI`, `THOI_GIAN_XAC_NHAN`) VALUES
(2, 57, 'trongnghia01', 'Đã xác nhận', '2025-10-26 19:15:48');

-- --------------------------------------------------------

--
-- Table structure for table `yeu_cau_thay_doi_lich`
--

CREATE TABLE `yeu_cau_thay_doi_lich` (
  `ID_YEUCAU` int(11) NOT NULL,
  `ID_LICHHEN` int(11) DEFAULT NULL,
  `ID_TK` varchar(20) DEFAULT NULL,
  `NOI_DUNG` text DEFAULT NULL,
  `TRANGTHAI` enum('Chờ duyệt','Đã duyệt','Từ chối') DEFAULT 'Chờ duyệt',
  `NGAY_GUI` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Structure for view `v_dich_vu_gia_moinhat`
--
DROP TABLE IF EXISTS `v_dich_vu_gia_moinhat`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dich_vu_gia_moinhat`  AS SELECT `dv`.`ID_DV` AS `ID_DV`, `dv`.`TEN_DV` AS `TEN_DV`, `dv`.`MOTA_DV` AS `MOTA_DV`, `dv`.`IMAGE` AS `IMAGE`, `dv`.`THOI_GIAN` AS `THOI_GIAN`, `dgdv`.`DON_GIA` AS `GIA_MOI_NHAT` FROM (`dich_vu` `dv` left join `don_gia_dich_vu` `dgdv` on(`dgdv`.`ID_DV` = `dv`.`ID_DV` and `dgdv`.`NGAY_GIO` = (select max(`x`.`NGAY_GIO`) from `don_gia_dich_vu` `x` where `x`.`ID_DV` = `dv`.`ID_DV`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_gallery_goi_dich_vu`
--
DROP TABLE IF EXISTS `v_gallery_goi_dich_vu`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_goi_dich_vu`  AS SELECT `gct`.`ID_GOI` AS `ID_GOI`, `dv`.`ID_DV` AS `ID_DV`, `dv`.`TEN_DV` AS `TEN_DV`, coalesce(`ha`.`URL`,`dv`.`IMAGE`) AS `URL`, `ha`.`ALT_TEXT` AS `ALT_TEXT`, `ha`.`IS_COVER` AS `IS_COVER`, `ha`.`THU_TU` AS `THU_TU` FROM ((`goi_dich_vu_chi_tiet` `gct` join `dich_vu` `dv` on(`dv`.`ID_DV` = `gct`.`ID_DV`)) left join `dich_vu_hinh_anh` `ha` on(`ha`.`ID_DV` = `gct`.`ID_DV` and `ha`.`IS_ACTIVE` = 1)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_goi_dich_vu_tong_tien`
--
DROP TABLE IF EXISTS `v_goi_dich_vu_tong_tien`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_goi_dich_vu_tong_tien`  AS SELECT `g`.`ID_GOI` AS `ID_GOI`, `g`.`TEN_GOI` AS `TEN_GOI`, `g`.`MO_TA` AS `MO_TA`, `g`.`HINH_ANH` AS `HINH_ANH`, `g`.`HIEU_LUC_TU` AS `HIEU_LUC_TU`, `g`.`HIEU_LUC_DEN` AS `HIEU_LUC_DEN`, `g`.`TRANG_THAI` AS `TRANG_THAI`, sum(coalesce(`ct`.`DON_GIA_AP_DUNG`,(select `d`.`DON_GIA` from `don_gia_dich_vu` `d` where `d`.`ID_DV` = `ct`.`ID_DV` order by `d`.`NGAY_GIO` desc limit 1)) * coalesce(`ct`.`SO_LUONG`,1)) AS `TONG_GIA_GOI` FROM (`goi_dich_vu` `g` join `goi_dich_vu_chi_tiet` `ct` on(`ct`.`ID_GOI` = `g`.`ID_GOI`)) GROUP BY `g`.`ID_GOI`, `g`.`TEN_GOI`, `g`.`MO_TA`, `g`.`HINH_ANH`, `g`.`HIEU_LUC_TU`, `g`.`HIEU_LUC_DEN`, `g`.`TRANG_THAI` ;

-- --------------------------------------------------------

--
-- Structure for view `v_trang_phuc_don_gia_moinhat`
--
DROP TABLE IF EXISTS `v_trang_phuc_don_gia_moinhat`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_trang_phuc_don_gia_moinhat`  AS SELECT `tp`.`ID_TP` AS `ID_TP`, `tp`.`TEN_TP` AS `TEN_TP`, `tp`.`ID_CN` AS `ID_CN`, `tp`.`SIZE` AS `SIZE`, `tp`.`MAU` AS `MAU`, `d`.`DON_GIA` AS `DON_GIA`, `d`.`NGAY_GIO` AS `HIEU_LUC_TU` FROM (`trang_phuc` `tp` join (select `x`.`ID_TP` AS `ID_TP`,`x`.`NGAY_GIO` AS `NGAY_GIO`,`x`.`DON_GIA` AS `DON_GIA` from (`don_gia_trang_phuc` `x` join (select `don_gia_trang_phuc`.`ID_TP` AS `ID_TP`,max(`don_gia_trang_phuc`.`NGAY_GIO`) AS `NG` from `don_gia_trang_phuc` group by `don_gia_trang_phuc`.`ID_TP`) `y` on(`x`.`ID_TP` = `y`.`ID_TP` and `x`.`NGAY_GIO` = `y`.`NG`))) `d` on(`d`.`ID_TP` = `tp`.`ID_TP`)) WHERE `tp`.`IS_ACTIVE` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_chat_message`
--
ALTER TABLE `ai_chat_message`
  ADD PRIMARY KEY (`ID_MSG`),
  ADD KEY `idx_aicm_session_time` (`ID_SESSION`,`CREATED_AT`);

--
-- Indexes for table `ai_chat_session`
--
ALTER TABLE `ai_chat_session`
  ADD PRIMARY KEY (`ID_SESSION`),
  ADD UNIQUE KEY `uq_ai_chat_session_token` (`CLIENT_TOKEN`),
  ADD KEY `fk_aics_tk` (`ID_TK`);

--
-- Indexes for table `ai_goi_y_dich_vu`
--
ALTER TABLE `ai_goi_y_dich_vu`
  ADD PRIMARY KEY (`ID_DEXUAT`),
  ADD KEY `fk_aigydv_tk` (`ID_TK`);

--
-- Indexes for table `bang_chung_thanh_toan`
--
ALTER TABLE `bang_chung_thanh_toan`
  ADD PRIMARY KEY (`ID_BCTT`),
  ADD KEY `fk_bctt_hoadon` (`ID_HD`),
  ADD KEY `fk_bctt_nguoixn` (`NGUOI_XAC_NHAN`);

--
-- Indexes for table `bao_gia_tam_tinh`
--
ALTER TABLE `bao_gia_tam_tinh`
  ADD PRIMARY KEY (`ID_BG`),
  ADD KEY `fk_bgia_lichhen` (`ID_LICHHEN`);

--
-- Indexes for table `branch_customer_notes`
--
ALTER TABLE `branch_customer_notes`
  ADD PRIMARY KEY (`ID_NOTE`),
  ADD UNIQUE KEY `uniq_branch_customer` (`ID_CN`,`ID_TK`),
  ADD KEY `fk_branch_notes_customer` (`ID_TK`);

--
-- Indexes for table `chi_nhanh`
--
ALTER TABLE `chi_nhanh`
  ADD PRIMARY KEY (`ID_CN`);

--
-- Indexes for table `chi_phi_phat_sinh`
--
ALTER TABLE `chi_phi_phat_sinh`
  ADD PRIMARY KEY (`ID_CP`),
  ADD KEY `FK_CHI_PHI_CN` (`ID_CN`);

--
-- Indexes for table `chi_tiet_hoa_don`
--
ALTER TABLE `chi_tiet_hoa_don`
  ADD PRIMARY KEY (`ID_CTHD`),
  ADD KEY `ID_HD` (`ID_HD`);

--
-- Indexes for table `dich_vu`
--
ALTER TABLE `dich_vu`
  ADD PRIMARY KEY (`ID_DV`),
  ADD KEY `idx_dich_vu_status` (`TRANG_THAI`,`IS_DELETED`);

--
-- Indexes for table `dich_vu_hinh_anh`
--
ALTER TABLE `dich_vu_hinh_anh`
  ADD PRIMARY KEY (`ID_HA`),
  ADD KEY `ix_dvha_dv` (`ID_DV`,`IS_COVER`,`THU_TU`);

--
-- Indexes for table `don_gia_dich_vu`
--
ALTER TABLE `don_gia_dich_vu`
  ADD PRIMARY KEY (`ID_DV`,`NGAY_GIO`),
  ADD KEY `FK_DON_GIA__CO__ON_GI_THOI_DIE` (`NGAY_GIO`);

--
-- Indexes for table `don_gia_trang_phuc`
--
ALTER TABLE `don_gia_trang_phuc`
  ADD PRIMARY KEY (`ID_TP`,`NGAY_GIO`);

--
-- Indexes for table `don_thue_trang_phuc`
--
ALTER TABLE `don_thue_trang_phuc`
  ADD PRIMARY KEY (`ID_TTP`),
  ADD KEY `fk_ttp_kh` (`ID_TK`),
  ADD KEY `fk_ttp_cn` (`ID_CN`),
  ADD KEY `idx_ttp_trangthai` (`TRANG_THAI`),
  ADD KEY `idx_ttp_ngaynhan` (`NGAY_NHAN`,`NGAY_TRA_DK`);

--
-- Indexes for table `don_thue_trang_phuc_ct`
--
ALTER TABLE `don_thue_trang_phuc_ct`
  ADD PRIMARY KEY (`ID_TTP`,`ID_TP`),
  ADD KEY `idx_ttpct_tp` (`ID_TP`);

--
-- Indexes for table `goi_dich_vu`
--
ALTER TABLE `goi_dich_vu`
  ADD PRIMARY KEY (`ID_GOI`);

--
-- Indexes for table `goi_dich_vu_chi_tiet`
--
ALTER TABLE `goi_dich_vu_chi_tiet`
  ADD PRIMARY KEY (`ID_GOI`,`ID_DV`),
  ADD KEY `fk_gdvct_dv` (`ID_DV`);

--
-- Indexes for table `goi_dich_vu_thiet_bi`
--
ALTER TABLE `goi_dich_vu_thiet_bi`
  ADD PRIMARY KEY (`ID_GOI`,`ID_TB`),
  ADD KEY `fk_gdvtb_tb` (`ID_TB`);

--
-- Indexes for table `hoa_don`
--
ALTER TABLE `hoa_don`
  ADD PRIMARY KEY (`ID_HD`),
  ADD KEY `FK_HOA_DON_CO_HOA__O_LICH_HEN` (`ID_LICHHEN`),
  ADD KEY `FK_HOA_DON_LAP_HOA___THOI_DIE` (`NGAY_GIO`);

--
-- Indexes for table `hoa_don_thue_trang_phuc`
--
ALTER TABLE `hoa_don_thue_trang_phuc`
  ADD PRIMARY KEY (`ID_HD`,`ID_TTP`,`LOAI`),
  ADD KEY `fk_hdttp_ttp` (`ID_TTP`);

--
-- Indexes for table `khach_hang`
--
ALTER TABLE `khach_hang`
  ADD PRIMARY KEY (`ID_TK`);

--
-- Indexes for table `lich_bao_tri_thiet_bi`
--
ALTER TABLE `lich_bao_tri_thiet_bi`
  ADD PRIMARY KEY (`ID_BTTB`),
  ADD KEY `fk_bttb_tb` (`ID_TB`),
  ADD KEY `idx_bttb_range` (`START_AT`,`END_AT`);

--
-- Indexes for table `lich_hen`
--
ALTER TABLE `lich_hen`
  ADD PRIMARY KEY (`ID_LICHHEN`),
  ADD KEY `FK_LICH_HEN_CUA_DICH__DICH_VU` (`ID_DV`),
  ADD KEY `FK_LICH_HEN__AT_LICH__KHACH_HA` (`ID_TK`),
  ADD KEY `fk_lichhen_chinhanh` (`ID_CHINHANH`),
  ADD KEY `fk_lichhen_goi` (`ID_GOI`),
  ADD KEY `idx_chinhanh_time` (`ID_CHINHANH`,`THOI_GIAN_BAT_DAU`),
  ADD KEY `idx_location_type` (`LOCATION_TYPE`);

--
-- Indexes for table `lich_lam_viec_nhan_vien`
--
ALTER TABLE `lich_lam_viec_nhan_vien`
  ADD PRIMARY KEY (`ID_LLV`),
  ADD KEY `fk_llv_taikhoan` (`ID_TK_NV`),
  ADD KEY `idx_llv_range` (`START_AT`,`END_AT`);

--
-- Indexes for table `luong_nhan_vien`
--
ALTER TABLE `luong_nhan_vien`
  ADD PRIMARY KEY (`ID_LUONG`),
  ADD KEY `ID_TK` (`ID_TK`);

--
-- Indexes for table `nghi_phep_nhan_vien`
--
ALTER TABLE `nghi_phep_nhan_vien`
  ADD PRIMARY KEY (`ID_NP`),
  ADD KEY `fk_np_nv` (`ID_TK_NV`),
  ADD KEY `fk_np_duyet` (`NGUOI_DUYET`),
  ADD KEY `idx_np_range` (`TU_NGAY`,`DEN_NGAY`);

--
-- Indexes for table `nhan_vien`
--
ALTER TABLE `nhan_vien`
  ADD PRIMARY KEY (`ID_TK`),
  ADD KEY `FK_NHAN_VIE_THUOC_CHI_NHAN` (`ID_CN`),
  ADD KEY `IDX_NV_ID_TK` (`ID_TK`),
  ADD KEY `IDX_NV_LOAI` (`LOAI_NV`);

--
-- Indexes for table `nhat_ky_he_thong`
--
ALTER TABLE `nhat_ky_he_thong`
  ADD PRIMARY KEY (`ID_LOG`),
  ADD KEY `fk_nkht_actor` (`ACTOR_ID`),
  ADD KEY `idx_nkht_main` (`HANH_DONG`,`CREATED_AT`);

--
-- Indexes for table `phan_cong_nhan_vien`
--
ALTER TABLE `phan_cong_nhan_vien`
  ADD PRIMARY KEY (`ID_TK`,`ID_LICHHEN`),
  ADD KEY `FK_PHAN_CON_PHAN_CONG_LICH_HEN` (`ID_LICHHEN`);

--
-- Indexes for table `phan_hoi_cua_khach_hang`
--
ALTER TABLE `phan_hoi_cua_khach_hang`
  ADD PRIMARY KEY (`ID_TK`,`ID_DV`),
  ADD KEY `FK_PHAN_HOI_PHAN_HOI__DICH_VU` (`ID_DV`);

--
-- Indexes for table `phu_phi_thue_trang_phuc`
--
ALTER TABLE `phu_phi_thue_trang_phuc`
  ADD PRIMARY KEY (`ID_PHI`),
  ADD KEY `fk_phuphi_ttp` (`ID_TTP`);

--
-- Indexes for table `quang_tri_vien`
--
ALTER TABLE `quang_tri_vien`
  ADD PRIMARY KEY (`ID_TK`);

--
-- Indexes for table `quanly_luong_chinhanh`
--
ALTER TABLE `quanly_luong_chinhanh`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_salary_employee_period` (`ID_TK_NV`,`THANG`,`NAM`),
  ADD KEY `idx_branch_period` (`ID_CN`,`THANG`,`NAM`),
  ADD KEY `idx_status` (`TRANG_THAI`);

--
-- Indexes for table `quyen_truy_cap`
--
ALTER TABLE `quyen_truy_cap`
  ADD PRIMARY KEY (`ID_QUYEN`);

--
-- Indexes for table `tai_chinh`
--
ALTER TABLE `tai_chinh`
  ADD PRIMARY KEY (`ID_TC`),
  ADD KEY `FK_TAI_CHIN_CO_GIAO_D_CHI_NHAN` (`ID_CN`);

--
-- Indexes for table `tai_khoan`
--
ALTER TABLE `tai_khoan`
  ADD PRIMARY KEY (`ID_TK`),
  ADD KEY `FK_TAI_KHOA_CO_QUYEN_TR` (`ID_QUYEN`);

--
-- Indexes for table `thanh_toan_truc_tuyen`
--
ALTER TABLE `thanh_toan_truc_tuyen`
  ADD PRIMARY KEY (`ID_TTTT`),
  ADD UNIQUE KEY `uq_tttt_ref` (`GATEWAY`,`MA_THAM_CHIEU`),
  ADD KEY `fk_tttt_hd` (`ID_HD`);

--
-- Indexes for table `thoi_diem`
--
ALTER TABLE `thoi_diem`
  ADD PRIMARY KEY (`NGAY_GIO`);

--
-- Indexes for table `thong_bao`
--
ALTER TABLE `thong_bao`
  ADD PRIMARY KEY (`ID_TBThongBao`),
  ADD KEY `fk_tb_nguoinhan` (`ID_TK_NGUOI_NHAN`),
  ADD KEY `idx_tb_status` (`TRANG_THAI_GUI`,`SENT_AT`);

--
-- Indexes for table `trang_phuc`
--
ALTER TABLE `trang_phuc`
  ADD PRIMARY KEY (`ID_TP`),
  ADD KEY `idx_tp_cn` (`ID_CN`),
  ADD KEY `idx_tp_loai` (`ID_LOAI`),
  ADD KEY `idx_tp_active` (`IS_ACTIVE`,`TINH_TRANG`);

--
-- Indexes for table `trang_phuc_hinh_anh`
--
ALTER TABLE `trang_phuc_hinh_anh`
  ADD PRIMARY KEY (`ID_HA`),
  ADD KEY `idx_tpha_tp_cover` (`ID_TP`,`IS_COVER`);

--
-- Indexes for table `trang_phuc_loai`
--
ALTER TABLE `trang_phuc_loai`
  ADD PRIMARY KEY (`ID_LOAI`);

--
-- Indexes for table `trang_thai_lich_hen_log`
--
ALTER TABLE `trang_thai_lich_hen_log`
  ADD PRIMARY KEY (`ID_LOG`),
  ADD KEY `fk_ttlh_lichhen` (`ID_LICHHEN`),
  ADD KEY `fk_ttlh_actor` (`ID_TK_THUC_HIEN`);

--
-- Indexes for table `trang_thiet_bi`
--
ALTER TABLE `trang_thiet_bi`
  ADD PRIMARY KEY (`ID_TB`),
  ADD KEY `FK_TRANG_TH_SO_HUU_CHI_NHAN` (`ID_CN`);

--
-- Indexes for table `xac_nhan_hoan_thanh`
--
ALTER TABLE `xac_nhan_hoan_thanh`
  ADD PRIMARY KEY (`ID_XACNHAN`),
  ADD KEY `FK_XACNHAN_LICHHEN` (`ID_LICHHEN`),
  ADD KEY `FK_XACNHAN_TK` (`ID_TK`);

--
-- Indexes for table `yeu_cau_thay_doi_lich`
--
ALTER TABLE `yeu_cau_thay_doi_lich`
  ADD PRIMARY KEY (`ID_YEUCAU`),
  ADD KEY `ID_LICHHEN` (`ID_LICHHEN`),
  ADD KEY `ID_TK` (`ID_TK`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_chat_message`
--
ALTER TABLE `ai_chat_message`
  MODIFY `ID_MSG` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `ai_chat_session`
--
ALTER TABLE `ai_chat_session`
  MODIFY `ID_SESSION` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ai_goi_y_dich_vu`
--
ALTER TABLE `ai_goi_y_dich_vu`
  MODIFY `ID_DEXUAT` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bang_chung_thanh_toan`
--
ALTER TABLE `bang_chung_thanh_toan`
  MODIFY `ID_BCTT` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bao_gia_tam_tinh`
--
ALTER TABLE `bao_gia_tam_tinh`
  MODIFY `ID_BG` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_customer_notes`
--
ALTER TABLE `branch_customer_notes`
  MODIFY `ID_NOTE` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chi_nhanh`
--
ALTER TABLE `chi_nhanh`
  MODIFY `ID_CN` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `chi_phi_phat_sinh`
--
ALTER TABLE `chi_phi_phat_sinh`
  MODIFY `ID_CP` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `chi_tiet_hoa_don`
--
ALTER TABLE `chi_tiet_hoa_don`
  MODIFY `ID_CTHD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dich_vu`
--
ALTER TABLE `dich_vu`
  MODIFY `ID_DV` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `dich_vu_hinh_anh`
--
ALTER TABLE `dich_vu_hinh_anh`
  MODIFY `ID_HA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `don_thue_trang_phuc`
--
ALTER TABLE `don_thue_trang_phuc`
  MODIFY `ID_TTP` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `goi_dich_vu`
--
ALTER TABLE `goi_dich_vu`
  MODIFY `ID_GOI` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hoa_don`
--
ALTER TABLE `hoa_don`
  MODIFY `ID_HD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `lich_bao_tri_thiet_bi`
--
ALTER TABLE `lich_bao_tri_thiet_bi`
  MODIFY `ID_BTTB` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lich_hen`
--
ALTER TABLE `lich_hen`
  MODIFY `ID_LICHHEN` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `lich_lam_viec_nhan_vien`
--
ALTER TABLE `lich_lam_viec_nhan_vien`
  MODIFY `ID_LLV` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `luong_nhan_vien`
--
ALTER TABLE `luong_nhan_vien`
  MODIFY `ID_LUONG` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=252;

--
-- AUTO_INCREMENT for table `nghi_phep_nhan_vien`
--
ALTER TABLE `nghi_phep_nhan_vien`
  MODIFY `ID_NP` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nhat_ky_he_thong`
--
ALTER TABLE `nhat_ky_he_thong`
  MODIFY `ID_LOG` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `phu_phi_thue_trang_phuc`
--
ALTER TABLE `phu_phi_thue_trang_phuc`
  MODIFY `ID_PHI` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quanly_luong_chinhanh`
--
ALTER TABLE `quanly_luong_chinhanh`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tai_chinh`
--
ALTER TABLE `tai_chinh`
  MODIFY `ID_TC` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `thanh_toan_truc_tuyen`
--
ALTER TABLE `thanh_toan_truc_tuyen`
  MODIFY `ID_TTTT` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `thong_bao`
--
ALTER TABLE `thong_bao`
  MODIFY `ID_TBThongBao` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trang_phuc`
--
ALTER TABLE `trang_phuc`
  MODIFY `ID_TP` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `trang_phuc_hinh_anh`
--
ALTER TABLE `trang_phuc_hinh_anh`
  MODIFY `ID_HA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `trang_phuc_loai`
--
ALTER TABLE `trang_phuc_loai`
  MODIFY `ID_LOAI` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `trang_thai_lich_hen_log`
--
ALTER TABLE `trang_thai_lich_hen_log`
  MODIFY `ID_LOG` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trang_thiet_bi`
--
ALTER TABLE `trang_thiet_bi`
  MODIFY `ID_TB` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `xac_nhan_hoan_thanh`
--
ALTER TABLE `xac_nhan_hoan_thanh`
  MODIFY `ID_XACNHAN` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `yeu_cau_thay_doi_lich`
--
ALTER TABLE `yeu_cau_thay_doi_lich`
  MODIFY `ID_YEUCAU` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_chat_message`
--
ALTER TABLE `ai_chat_message`
  ADD CONSTRAINT `fk_aicm_session` FOREIGN KEY (`ID_SESSION`) REFERENCES `ai_chat_session` (`ID_SESSION`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ai_chat_session`
--
ALTER TABLE `ai_chat_session`
  ADD CONSTRAINT `fk_aics_tk` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ai_goi_y_dich_vu`
--
ALTER TABLE `ai_goi_y_dich_vu`
  ADD CONSTRAINT `fk_aigydv_tk` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bang_chung_thanh_toan`
--
ALTER TABLE `bang_chung_thanh_toan`
  ADD CONSTRAINT `fk_bctt_hoadon` FOREIGN KEY (`ID_HD`) REFERENCES `hoa_don` (`ID_HD`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bctt_nguoixn` FOREIGN KEY (`NGUOI_XAC_NHAN`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bao_gia_tam_tinh`
--
ALTER TABLE `bao_gia_tam_tinh`
  ADD CONSTRAINT `fk_bgia_lichhen` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `branch_customer_notes`
--
ALTER TABLE `branch_customer_notes`
  ADD CONSTRAINT `fk_branch_notes_branch` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_branch_notes_customer` FOREIGN KEY (`ID_TK`) REFERENCES `khach_hang` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chi_phi_phat_sinh`
--
ALTER TABLE `chi_phi_phat_sinh`
  ADD CONSTRAINT `FK_CHI_PHI_CN` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chi_tiet_hoa_don`
--
ALTER TABLE `chi_tiet_hoa_don`
  ADD CONSTRAINT `chi_tiet_hoa_don_ibfk_1` FOREIGN KEY (`ID_HD`) REFERENCES `hoa_don` (`ID_HD`) ON DELETE CASCADE;

--
-- Constraints for table `dich_vu_hinh_anh`
--
ALTER TABLE `dich_vu_hinh_anh`
  ADD CONSTRAINT `fk_dvha_dv` FOREIGN KEY (`ID_DV`) REFERENCES `dich_vu` (`ID_DV`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `don_gia_dich_vu`
--
ALTER TABLE `don_gia_dich_vu`
  ADD CONSTRAINT `FK_DON_GIA__CO__ON_GI_DICH_VU` FOREIGN KEY (`ID_DV`) REFERENCES `dich_vu` (`ID_DV`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_DON_GIA__CO__ON_GI_THOI_DIE` FOREIGN KEY (`NGAY_GIO`) REFERENCES `thoi_diem` (`NGAY_GIO`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `don_gia_trang_phuc`
--
ALTER TABLE `don_gia_trang_phuc`
  ADD CONSTRAINT `fk_dgtp_tp` FOREIGN KEY (`ID_TP`) REFERENCES `trang_phuc` (`ID_TP`) ON DELETE CASCADE;

--
-- Constraints for table `don_thue_trang_phuc`
--
ALTER TABLE `don_thue_trang_phuc`
  ADD CONSTRAINT `fk_ttp_cn` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`),
  ADD CONSTRAINT `fk_ttp_kh` FOREIGN KEY (`ID_TK`) REFERENCES `khach_hang` (`ID_TK`);

--
-- Constraints for table `don_thue_trang_phuc_ct`
--
ALTER TABLE `don_thue_trang_phuc_ct`
  ADD CONSTRAINT `fk_ttpct_tp` FOREIGN KEY (`ID_TP`) REFERENCES `trang_phuc` (`ID_TP`),
  ADD CONSTRAINT `fk_ttpct_ttp` FOREIGN KEY (`ID_TTP`) REFERENCES `don_thue_trang_phuc` (`ID_TTP`) ON DELETE CASCADE;

--
-- Constraints for table `goi_dich_vu_chi_tiet`
--
ALTER TABLE `goi_dich_vu_chi_tiet`
  ADD CONSTRAINT `fk_gdvct_dv` FOREIGN KEY (`ID_DV`) REFERENCES `dich_vu` (`ID_DV`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gdvct_goi` FOREIGN KEY (`ID_GOI`) REFERENCES `goi_dich_vu` (`ID_GOI`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `goi_dich_vu_thiet_bi`
--
ALTER TABLE `goi_dich_vu_thiet_bi`
  ADD CONSTRAINT `fk_gdvtb_goi` FOREIGN KEY (`ID_GOI`) REFERENCES `goi_dich_vu` (`ID_GOI`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gdvtb_tb` FOREIGN KEY (`ID_TB`) REFERENCES `trang_thiet_bi` (`ID_TB`) ON UPDATE CASCADE;

--
-- Constraints for table `hoa_don`
--
ALTER TABLE `hoa_don`
  ADD CONSTRAINT `FK_HOA_DON_CO_HOA__O_LICH_HEN` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hoa_don_thue_trang_phuc`
--
ALTER TABLE `hoa_don_thue_trang_phuc`
  ADD CONSTRAINT `fk_hdttp_hd` FOREIGN KEY (`ID_HD`) REFERENCES `hoa_don` (`ID_HD`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hdttp_ttp` FOREIGN KEY (`ID_TTP`) REFERENCES `don_thue_trang_phuc` (`ID_TTP`) ON DELETE CASCADE;

--
-- Constraints for table `khach_hang`
--
ALTER TABLE `khach_hang`
  ADD CONSTRAINT `FK_KHACH_HA_CO_TK2_TAI_KHOA` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lich_bao_tri_thiet_bi`
--
ALTER TABLE `lich_bao_tri_thiet_bi`
  ADD CONSTRAINT `fk_bttb_tb` FOREIGN KEY (`ID_TB`) REFERENCES `trang_thiet_bi` (`ID_TB`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lich_hen`
--
ALTER TABLE `lich_hen`
  ADD CONSTRAINT `FK_LICH_HEN_CUA_DICH__DICH_VU` FOREIGN KEY (`ID_DV`) REFERENCES `dich_vu` (`ID_DV`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_LICH_HEN__AT_LICH__KHACH_HA` FOREIGN KEY (`ID_TK`) REFERENCES `khach_hang` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lichhen_chinhanh` FOREIGN KEY (`ID_CHINHANH`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lichhen_goi` FOREIGN KEY (`ID_GOI`) REFERENCES `goi_dich_vu` (`ID_GOI`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `lich_lam_viec_nhan_vien`
--
ALTER TABLE `lich_lam_viec_nhan_vien`
  ADD CONSTRAINT `fk_llv_taikhoan` FOREIGN KEY (`ID_TK_NV`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `luong_nhan_vien`
--
ALTER TABLE `luong_nhan_vien`
  ADD CONSTRAINT `luong_nhan_vien_ibfk_1` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE;

--
-- Constraints for table `nghi_phep_nhan_vien`
--
ALTER TABLE `nghi_phep_nhan_vien`
  ADD CONSTRAINT `fk_np_duyet` FOREIGN KEY (`NGUOI_DUYET`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_np_nv` FOREIGN KEY (`ID_TK_NV`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `nhan_vien`
--
ALTER TABLE `nhan_vien`
  ADD CONSTRAINT `FK_NHAN_VIE_CO_TK3_TAI_KHOA` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_NHAN_VIE_THUOC_CHI_NHAN` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `nhat_ky_he_thong`
--
ALTER TABLE `nhat_ky_he_thong`
  ADD CONSTRAINT `fk_nkht_actor` FOREIGN KEY (`ACTOR_ID`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `phan_cong_nhan_vien`
--
ALTER TABLE `phan_cong_nhan_vien`
  ADD CONSTRAINT `FK_PHAN_CON_PHAN_CONG_LICH_HEN` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PHAN_CON_PHAN_CONG_NHAN_VIE` FOREIGN KEY (`ID_TK`) REFERENCES `nhan_vien` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `phan_hoi_cua_khach_hang`
--
ALTER TABLE `phan_hoi_cua_khach_hang`
  ADD CONSTRAINT `FK_PHAN_HOI_GUI_KHACH_HA` FOREIGN KEY (`ID_TK`) REFERENCES `khach_hang` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PHAN_HOI_PHAN_HOI__DICH_VU` FOREIGN KEY (`ID_DV`) REFERENCES `dich_vu` (`ID_DV`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `phu_phi_thue_trang_phuc`
--
ALTER TABLE `phu_phi_thue_trang_phuc`
  ADD CONSTRAINT `fk_phuphi_ttp` FOREIGN KEY (`ID_TTP`) REFERENCES `don_thue_trang_phuc` (`ID_TTP`) ON DELETE CASCADE;

--
-- Constraints for table `quang_tri_vien`
--
ALTER TABLE `quang_tri_vien`
  ADD CONSTRAINT `FK_QUANG_TR_CO_TK_TAI_KHOA` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tai_chinh`
--
ALTER TABLE `tai_chinh`
  ADD CONSTRAINT `FK_TAI_CHIN_CO_GIAO_D_CHI_NHAN` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tai_khoan`
--
ALTER TABLE `tai_khoan`
  ADD CONSTRAINT `FK_TAI_KHOA_CO_QUYEN_TR` FOREIGN KEY (`ID_QUYEN`) REFERENCES `quyen_truy_cap` (`ID_QUYEN`);

--
-- Constraints for table `thanh_toan_truc_tuyen`
--
ALTER TABLE `thanh_toan_truc_tuyen`
  ADD CONSTRAINT `fk_tttt_hd` FOREIGN KEY (`ID_HD`) REFERENCES `hoa_don` (`ID_HD`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `thong_bao`
--
ALTER TABLE `thong_bao`
  ADD CONSTRAINT `fk_tb_nguoinhan` FOREIGN KEY (`ID_TK_NGUOI_NHAN`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `trang_phuc`
--
ALTER TABLE `trang_phuc`
  ADD CONSTRAINT `fk_tp_cn` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`),
  ADD CONSTRAINT `fk_tp_loai` FOREIGN KEY (`ID_LOAI`) REFERENCES `trang_phuc_loai` (`ID_LOAI`);

--
-- Constraints for table `trang_phuc_hinh_anh`
--
ALTER TABLE `trang_phuc_hinh_anh`
  ADD CONSTRAINT `fk_tpha_tp` FOREIGN KEY (`ID_TP`) REFERENCES `trang_phuc` (`ID_TP`) ON DELETE CASCADE;

--
-- Constraints for table `trang_thai_lich_hen_log`
--
ALTER TABLE `trang_thai_lich_hen_log`
  ADD CONSTRAINT `fk_ttlh_actor` FOREIGN KEY (`ID_TK_THUC_HIEN`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ttlh_lichhen` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `trang_thiet_bi`
--
ALTER TABLE `trang_thiet_bi`
  ADD CONSTRAINT `FK_TRANG_TH_SO_HUU_CHI_NHAN` FOREIGN KEY (`ID_CN`) REFERENCES `chi_nhanh` (`ID_CN`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `xac_nhan_hoan_thanh`
--
ALTER TABLE `xac_nhan_hoan_thanh`
  ADD CONSTRAINT `FK_XACNHAN_LICHHEN` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_XACNHAN_TK` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`) ON DELETE CASCADE;

--
-- Constraints for table `yeu_cau_thay_doi_lich`
--
ALTER TABLE `yeu_cau_thay_doi_lich`
  ADD CONSTRAINT `yeu_cau_thay_doi_lich_ibfk_1` FOREIGN KEY (`ID_LICHHEN`) REFERENCES `lich_hen` (`ID_LICHHEN`),
  ADD CONSTRAINT `yeu_cau_thay_doi_lich_ibfk_2` FOREIGN KEY (`ID_TK`) REFERENCES `tai_khoan` (`ID_TK`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
