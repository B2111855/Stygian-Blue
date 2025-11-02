-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:7070
-- Generation Time: Oct 30, 2025 at 01:59 AM
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

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_session`
--

CREATE TABLE `ai_chat_session` (
  `ID_SESSION` bigint(20) NOT NULL,
  `ID_TK` varchar(20) DEFAULT NULL,
  `KENH` enum('web','zalo','messenger','khac') DEFAULT 'web',
  `BAT_DAU` datetime NOT NULL DEFAULT current_timestamp(),
  `KET_THUC` datetime DEFAULT NULL,
  `TRANG_THAI` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

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
-- Table structure for table `chi_nhanh`
--

CREATE TABLE `chi_nhanh` (
  `ID_CN` int(11) NOT NULL,
  `TEN_CN` varchar(100) DEFAULT NULL,
  `SDT_CN` varchar(12) DEFAULT NULL,
  `DIA_CHI_CN` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `chi_nhanh`
--

INSERT INTO `chi_nhanh` (`ID_CN`, `TEN_CN`, `SDT_CN`, `DIA_CHI_CN`) VALUES
(1, 'Hà Nội', '0123456789', '123 Đường Cầu Giấy, Hà Nội'),
(2, 'Hồ Chí Minh', '0987654321', '456 Đường Lê Lợi, TP. HCM'),
(3, 'Đà Nẵng', '0222333444', '789 Đường Nguyễn Văn Linh, Đà Nẵng'),
(4, 'Hải Phòng', '0333444555', '101 Đường Trần Phú, Hải Phòng'),
(5, 'Cần Thơ', '0444555666', '203 Đường Võ Văn Kiệt, Cần Thơ');

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
  `THOI_GIAN` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `dich_vu`
--

INSERT INTO `dich_vu` (`ID_DV`, `TEN_DV`, `MOTA_DV`, `IMAGE`, `THOI_GIAN`) VALUES
(1, 'Chụp hình cưới', 'Lưu giữ trọn vẹn từng khoảnh khắc hạnh phúc trong ngày trọng đại của cặp đôi. Bộ ảnh cưới sẽ được thực hiện với sự sáng tạo, lãng mạn, và đậm chất nghệ thuật, mang đậm dấu ấn riêng của cô dâu chú rể.', 'public/images/dichvu/anhcuoi.jpg', 480),
(2, 'Chụp hình chân dung', 'Ghi lại nét đẹp cá nhân qua các bức ảnh chân dung chuyên nghiệp, từ phong cách cổ điển đến hiện đại. Đây là cách tuyệt vời để lưu giữ hình ảnh cá nhân hoặc sử dụng cho mục đích truyền thông cá nhân.', 'public/images/dichvu/chandung.jpg', 120),
(4, 'Trang điểm', 'Cung cấp dịch vụ trang điểm chuyên nghiệp phù hợp với các dịp chụp ảnh như sự kiện, cưới hỏi, hoặc quảng cáo. Phong cách đa dạng từ tự nhiên đến lộng lẫy, đảm bảo làm nổi bật vẻ đẹp của khách hàng.', 'public/images/dichvu/pexels-kinkate-208052.jpg', 180),
(6, 'Chụp ảnh gia đình', 'Ghi lại những khoảnh khắc yêu thương, đầm ấm của cả gia đình qua các bức ảnh tự nhiên, đầy cảm xúc. Dịch vụ này phù hợp để lưu giữ kỷ niệm hoặc sử dụng trong các dịp đặc biệt.', 'public/images/dichvu/pexels-ketut-subiyanto-4546025.jpg', 180),
(7, 'Chụp hình quảng cáo', 'Tạo ra những bức ảnh chuyên nghiệp nhằm phục vụ cho mục đích quảng bá sản phẩm, dịch vụ hoặc thương hiệu. Đảm bảo hình ảnh bắt mắt, sắc nét, và thể hiện rõ thông điệp mà doanh nghiệp muốn truyền tải.', 'public/images/dichvu/pexels-laryssa-suaid-798122-1667088.jpg', 60),
(8, 'Chụp ảnh thẻ', 'Cung cấp ảnh thẻ nhanh chóng, đúng tiêu chuẩn, sắc nét và phù hợp với nhiều mục đích sử dụng như hồ sơ cá nhân, hồ sơ công việc, visa, hộ chiếu.', 'public/images/dichvu/339556239_670242441778333_2053857324053008066_n.jpg', 180),
(9, 'Chụp ảnh sự kiện', 'Dịch vụ chụp ảnh chuyên nghiệp cho các sự kiện như hội nghị, lễ khai trương, các buổi họp mặt, hoặc sự kiện giải trí. Đảm bảo bắt trọn những khoảnh khắc quan trọng và tạo nên hình ảnh ấn tượng để lưu giữ hoặc sử dụng cho truyền thông.', 'public/images/dichvu/sukien.jpg', 480),
(25, 'Chụp ảnh kỷ yếu ', 'abcd', 'public/images/dichvu/kyyeu.jpg', 480);

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
(37, '2025-04-21 04:55:03', 53, 2400000, 'Chưa thanh toán', 'Chuyển khoản ngân hàng', 1),
(38, '2025-04-21 05:02:02', 54, 2400000, 'Chưa thanh toán', 'Chuyển khoản ngân hàng', 0),
(39, '2025-04-21 05:23:27', 55, 600000, 'Chưa thanh toán', 'Chuyển khoản ngân hàng', 1),
(41, '2025-04-23 16:09:03', 57, 4000000, 'Đã thanh toán', 'Chuyển khoản ngân hàng', 1);

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
('huynhtuankiet', 3, 'Huỳnh Tuấn Kiệt', '2025-04-01', 'Kiên Giang', 'htkiet@gmail.com', '0782952433', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyentrungkien', 3, 'Nguyễn Trung Kiên', '2025-04-01', 'Cà Mau', 'ntkien@gmail.com', '0788234234', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyenvietcuong', 3, 'Nguyễn Viết Cường', '2025-04-01', 'Bạc Liêu', 'nvcuong@gmail.com', '0909145765', '$2y$10$uqE8lWUMX/4DZMZHPxwjp.DCD7xSdHeDnUwzZpdWA/E6n.W5VPl/.'),
('phanthioanh', 3, 'Phan Thị Oanh', '1953-10-09', 'ấp Thiện Nhơn, Xã Thuận Hưng, huyện Mỹ tú, tỉnh Sóc Trăng', 'phanthioanh@gmail.com', '0907814560', '$2y$10$fUSCLq9HaI13vSf.ZYX14u9JFWCHxUzkrXe3a7WAvE.1dzNtPK4Je'),
('trongnghia', 3, 'Trọng Nghĩa', '2003-01-21', 'phường 4, Thành phố Sóc Trăng', 'trongnghiann4911@gmail.com', '0909300040', '$2y$10$5zKd131XRDnxjGL2eTAKGeDkUyhGGDWgw6Jk640r.2xMmsakvVfiy');

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
  `DIA_CHI_HEN` longtext DEFAULT NULL,
  `TRANGTHAI` varchar(20) DEFAULT NULL,
  `ID_CHINHANH` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Dumping data for table `lich_hen`
--

INSERT INTO `lich_hen` (`ID_LICHHEN`, `ID_DV`, `ID_GOI`, `ID_TK`, `THOI_GIAN_BAT_DAU`, `DIA_CHI_HEN`, `TRANGTHAI`, `ID_CHINHANH`) VALUES
(38, 8, NULL, 'dohonganh', '2025-04-19 09:00:00', 'chi nhánh Hà Nội', 'Đã hoàn thành', 1),
(39, 9, NULL, 'nguyenvietcuong', '2025-04-16 15:00:00', 'công viên Tao Đàn', 'Đã hoàn thành', 2),
(40, 9, NULL, 'daominhphuong', '2025-04-23 10:00:00', 'Nhà riêng, 12b2 KDC 30, Nguyễn Văn Linh, Hưng Lợi', 'Đã hoàn thành', 5),
(41, 1, NULL, 'nguyentrungkien', '2025-04-30 08:00:00', 'tại studio', 'Đã hoàn thành', 4),
(43, 7, NULL, 'trongnghia', '2025-04-19 10:00:00', 'tại studio', 'Đã hoàn thành', 1),
(44, 4, NULL, 'trongnghia', '2025-04-19 13:00:00', 'tại studio', 'Đã hoàn thành', 5),
(50, 2, NULL, 'trongnghia', '2025-04-20 08:00:00', 'abc', 'Đã xác nhận', 5),
(51, 1, NULL, 'trongnghia', '2025-04-20 08:00:00', 'acc', 'Đã xác nhận', 3),
(53, 9, NULL, 'dohonganh', '2025-04-30 08:00:00', 'Vinh độc lập', 'Đã xác nhận', 2),
(54, 9, NULL, 'dohonganh', '2025-04-30 09:00:00', 'Bến cảng nhà rồng', 'Đã xác nhận', 2),
(55, 6, NULL, 'dohonganh', '2025-04-30 08:00:00', 'Hồ Gươm', 'Đã xác nhận', 1),
(57, 9, NULL, 'dohonganh', '2025-04-30 08:00:00', 'ctu', 'Đã hoàn thành', 5),
(58, 2, NULL, 'trongnghia', '2025-10-26 08:00:00', '', 'Đã hủy', 2),
(59, 9, NULL, 'dohonganh', '2025-10-28 15:00:00', '', 'Đang chờ', 2),
(60, 2, NULL, 'dohonganh', '2025-10-28 08:00:00', 'a', 'Đang chờ', 2),
(61, 1, NULL, 'dohonganh', '2025-10-28 09:00:00', 'aa', 'Đang chờ', 2);

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
(233, 'trongnghia01', 4, 2025, 5000000, 0.15, 105000, 5105000, '2025-04-23');

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
('dangthibichthao', 5, 2, 'Đặng Thị Bích Thảo', '1983-06-23', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'dangthibichthao@gmail.com', '0909300040', '$2y$10$ofQ9Dz3ClNER5xzVYWAufO9FlTSwLm4RSHnToY0IkImQXGoGsYwGe', 'Chuyên viên make-up', 'chuyen_trach'),
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
(137, 5, 'doanh thu', NULL, 4000000, '2025-04-23 16:39:18', 41);

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
('captianbidu', 1, 'Captian Bidu', '2022-10-26', 'Cần Thơ', 'admin@gmail.com', '0907814560', '$2y$10$bng5C3H1zujKaKYlzoQnKuGwquK6kIrhbONURuyNrn1YNFwNPvzQK'),
('chauquocvinh', 2, 'Châu Quốc Vinh', '2025-04-09', 'Vĩnh Long', 'vinh@gmail.com', '0782345678', '$2y$10$trzVZbPOPbg7kuecz..J0eOj0.0rMBPHroHPdHdAyb7EP/vKzbE.y'),
('chauthimyhanh', 2, 'Châu Thị Mỹ Hạnh', '2024-12-03', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814566', '$2y$10$g0ZVia2OwMHPDW/0VMSohuReRVYAdcPj/C1vsJSk49vWCAAUJvVyq'),
('chauvanuot', 3, 'Châu Văn Uốt', '1955-06-11', 'Ấp Xóm Đồng 1, Huyện Kế Sách, Tỉnh Sóc Trăng', 'chauvanuot@gmail.com', '00', '$2y$10$fCjWC8brkgXmvAxrZbwXGOxwcQflOmf6XmH3pnYKGEWjT8HKgnQRW'),
('dangthibichthao', 2, 'Đặng Thị Bích Thảo', '1983-06-23', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'dangthibichthao@gmail.com', '0909300040', '$2y$10$bd8Xv8.Vjk4wjNb223kPDOfG8eh8GI3/Zh/LuhFhXR19J75hj.omy'),
('daominhphuong', 3, 'Đào Minh Phương', '2025-01-28', 'Long An', 'dmphuong@gmail.com', '0909300060', '$2y$10$q953nSwLjLr9eZod9MdxJ.HyKgGAAmesUJlKSIUM5ec7wpQLBUOiO'),
('doanminhdat', 3, 'Đoàn Minh Đạt ', '2025-04-16', 'Sóc Trăng', 'minhdat@gmail.com', '0907814560', '$2y$10$TNHas8Zv2.HV4lpZpGgRl.iBrX0q6y0amH.InLIY9vhBDmC362u32'),
('doantrongnghia', 3, 'Đoàn Trọng Nghĩa', '2025-04-09', 'Cần Thơ', 'doantrongnghia@gmail.com', '0909300040', '$2y$10$m5QJnVVSEWdSdEqCDCh2UemZhTUmT98gvvyk9jqgjxtsI7rOXjgnW'),
('doanxuannghi', 2, 'Đoàn Xuân Nghi', '2024-12-24', '546/8 Lê Duẩn, Khóm 4, Phường 4, TP. Sóc Trăng', 'vovanvu@gmai.com', '0907814560', '$2y$10$FQE1w/A.Ep/jPC/bKo8sLu0xsSo7jXQT7kRNreUTV2z/NMnfh3a4O'),
('dohonganh', 3, 'Đỗ Hồng Anh', '2025-04-07', '123 Nguyễn Văn Linh, Ninh Kiều, Cần Thơ', 'kensobollyet@gmail.com', '0907814560', '$2y$10$vkp.QvpTnx4bOunRXtSFHuYdJPIlvtjbiHC7nPlXX6Vuk0Zdu.sWm'),
('huynhtuankiet', 3, 'Huỳnh Tuấn Kiệt', '2025-04-01', 'Kiên Giang', 'htkiet@gmail.com', '0782952433', '$2y$10$8eccDZkfgKJu/OFDPE0gquo.ivMRDsgSPoW51An97vKbTC1.9Ln/m'),
('ltdiem', 2, 'Lê Thị Diễm', '1989-07-04', 'Vĩnh Long', 'nghia@gmail.com', '0939539596', '$2y$10$cQDaO.GoDZCmuG8YJ5Lmi.2mM5WlCv850hrLA6xFQa2f5B6MTb57C'),
('nguyentrungkien', 3, 'Nguyễn Trung Kiên', '2025-04-01', 'Cà Mau', 'ntkien@gmail.com', '0788234234', '$2y$10$cYd/FNLbIyseXLqLqy.ESer20qKYVUlTd4cfwMa6VZ15D.HCPOhIu'),
('nguyenvietcuong', 3, 'Nguyễn Viết Cường', '2025-04-01', 'Bạc Liêu', 'nvcuong@gmail.com', '0909145765', '$2y$10$uqE8lWUMX/4DZMZHPxwjp.DCD7xSdHeDnUwzZpdWA/E6n.W5VPl/.'),
('phanthioanh', 3, 'Phan Thị Oanh', '1953-10-09', 'ấp Thiện Nhơn, Xã Thuận Hưng, huyện Mỹ tú, tỉnh Sóc Trăng', 'phanthioanh@gmail.com', '0907814560', '$2y$10$fUSCLq9HaI13vSf.ZYX14u9JFWCHxUzkrXe3a7WAvE.1dzNtPK4Je'),
('taomanhduc', 2, 'Tào Mạnh Đức', '2025-04-01', 'Chợ Lớn', 'taomanhduc@gmail.com', '0909731202', '$2y$10$o/GEiOa0OS8VU0S9Ql3vlubCEIQZvjlTldU9cjkF6ZZ39SWVZDeAS'),
('trantuantu', 3, 'Trần Tuấn Tú', '1993-07-17', 'Mỹ Tú', 'trantuantu@gmail.com', '12', '$2y$10$u6j1NSYtKvu1neto.xawlOEGGlYzWNFEgBQgW1.242Ot0FZUP3ffy'),
('trongnghia', 3, 'Trọng Nghĩa', '2003-01-21', 'phường 4, Thành phố Sóc Trăng', 'trongnghiann4911@gmail.com', '0909300040', '$2y$10$Y5eCcbjFPO.R78JLuhCJ9.2iz2YiJFzzwYTsJq1NBCFfg83RUWJv6'),
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
  ADD PRIMARY KEY (`ID_DV`);

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
  ADD KEY `fk_lichhen_goi` (`ID_GOI`);

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
  MODIFY `ID_MSG` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_chat_session`
--
ALTER TABLE `ai_chat_session`
  MODIFY `ID_SESSION` bigint(20) NOT NULL AUTO_INCREMENT;

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
  MODIFY `ID_CTHD` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `ID_TTP` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goi_dich_vu`
--
ALTER TABLE `goi_dich_vu`
  MODIFY `ID_GOI` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hoa_don`
--
ALTER TABLE `hoa_don`
  MODIFY `ID_HD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `lich_bao_tri_thiet_bi`
--
ALTER TABLE `lich_bao_tri_thiet_bi`
  MODIFY `ID_BTTB` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lich_hen`
--
ALTER TABLE `lich_hen`
  MODIFY `ID_LICHHEN` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `lich_lam_viec_nhan_vien`
--
ALTER TABLE `lich_lam_viec_nhan_vien`
  MODIFY `ID_LLV` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `luong_nhan_vien`
--
ALTER TABLE `luong_nhan_vien`
  MODIFY `ID_LUONG` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=234;

--
-- AUTO_INCREMENT for table `nghi_phep_nhan_vien`
--
ALTER TABLE `nghi_phep_nhan_vien`
  MODIFY `ID_NP` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nhat_ky_he_thong`
--
ALTER TABLE `nhat_ky_he_thong`
  MODIFY `ID_LOG` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phu_phi_thue_trang_phuc`
--
ALTER TABLE `phu_phi_thue_trang_phuc`
  MODIFY `ID_PHI` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tai_chinh`
--
ALTER TABLE `tai_chinh`
  MODIFY `ID_TC` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `thanh_toan_truc_tuyen`
--
ALTER TABLE `thanh_toan_truc_tuyen`
  MODIFY `ID_TTTT` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thong_bao`
--
ALTER TABLE `thong_bao`
  MODIFY `ID_TBThongBao` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trang_phuc`
--
ALTER TABLE `trang_phuc`
  MODIFY `ID_TP` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trang_phuc_hinh_anh`
--
ALTER TABLE `trang_phuc_hinh_anh`
  MODIFY `ID_HA` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trang_phuc_loai`
--
ALTER TABLE `trang_phuc_loai`
  MODIFY `ID_LOAI` int(11) NOT NULL AUTO_INCREMENT;

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
