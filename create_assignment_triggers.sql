-- SQL script to create a trigger that automatically creates assignment records
-- when assignment-type materials are added to program_materials

USE tplearn;

-- Drop trigger if it already exists
DROP TRIGGER IF EXISTS auto_create_assignment_record;

-- Create trigger to automatically create assignment records
DELIMITER $$

CREATE TRIGGER auto_create_assignment_record
AFTER INSERT ON program_materials
FOR EACH ROW
BEGIN
    -- Only create assignment record if material_type is 'assignment'
    IF NEW.material_type = 'assignment' THEN
        INSERT INTO assignments (
            material_id, 
            due_date, 
            total_points, 
            allow_late_submissions, 
            instructions
        ) VALUES (
            NEW.id,
            DATE_ADD(NOW(), INTERVAL 30 DAY), -- Due 30 days from creation
            100, -- Default 100 points
            1, -- Allow late submissions by default
            CONCAT('Complete and submit your work for the ', NEW.title, ' assignment.')
        );
    END IF;
END$$

DELIMITER ;

-- Also create a trigger for updates (in case material_type changes to 'assignment')
DROP TRIGGER IF EXISTS auto_create_assignment_record_on_update;

DELIMITER $$

CREATE TRIGGER auto_create_assignment_record_on_update
AFTER UPDATE ON program_materials
FOR EACH ROW
BEGIN
    -- If material_type changed to 'assignment' and no assignment record exists
    IF NEW.material_type = 'assignment' AND OLD.material_type != 'assignment' THEN
        -- Check if assignment record already exists
        IF NOT EXISTS (SELECT 1 FROM assignments WHERE material_id = NEW.id) THEN
            INSERT INTO assignments (
                material_id, 
                due_date, 
                total_points, 
                allow_late_submissions, 
                instructions
            ) VALUES (
                NEW.id,
                DATE_ADD(NOW(), INTERVAL 30 DAY),
                100,
                1,
                CONCAT('Complete and submit your work for the ', NEW.title, ' assignment.')
            );
        END IF;
    END IF;
END$$

DELIMITER ;